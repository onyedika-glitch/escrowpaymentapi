<?php

namespace Tests\Feature;

use App\Models\Milestone;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MilestoneLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private int $client;
    private int $freelancer;
    private int $project;

    protected function setUp(): void
    {
        parent::setUp();
        config(['escrow.webhook_url' => 'https://hooks.test/escrow']);
        Http::fake(['hooks.test/*' => Http::response(['ok' => true])]);

        $this->client = $this->register('client', 'c@example.com');
        $this->freelancer = $this->register('freelancer', 'f@example.com');
        $this->project = $this->postJson('/projects', [
            'title' => 'P', 'freelancer_ids' => [$this->freelancer],
        ], $this->as($this->client))->assertCreated()->json('data.id');
    }

    public function test_full_lifecycle_moves_money_and_fires_webhooks(): void
    {
        $m = $this->createMilestone(250.75);

        $this->postJson("/wallets/{$this->client}/fund", ['amount' => 1000], $this->as($this->client))->assertCreated();

        $this->postJson("/projects/{$this->project}/milestones/{$m}/fund", [], $this->as($this->client))
            ->assertOk()->assertJsonPath('data.status', 'Funded');
        $this->assertBalances($this->client, 749.25, 250.75);

        $this->postJson("/projects/{$this->project}/milestones/{$m}/release", [], $this->as($this->client))
            ->assertOk()->assertJsonPath('data.status', 'Released');
        $this->assertBalances($this->client, 749.25, 0);
        $this->assertBalances($this->freelancer, 250.75, 0);

        $this->getJson("/wallets/{$this->client}/history")->assertJsonCount(3, 'data.entries');
        $this->getJson("/wallets/{$this->freelancer}/history")
            ->assertJsonPath('data.entries.0.type', 'payment_received');

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['event'] === 'milestone.released'
            && $r['data']['milestone_id'] === $m
            && $r['data']['project_id'] === $this->project
            && $r['data']['freelancer']['kyc']['legal_full_name'] === 'Legal freelancer'
            && ! str_contains(json_encode($r->data()), 'ID123456789'));
    }

    public function test_cannot_fund_milestone_with_insufficient_balance(): void
    {
        $m = $this->createMilestone(100);
        $this->postJson("/wallets/{$this->client}/fund", ['amount' => 99.99], $this->as($this->client));

        $this->postJson("/projects/{$this->project}/milestones/{$m}/fund", [], $this->as($this->client))
            ->assertStatus(422)->assertJsonPath('error.code', 'insufficient_funds');

        $this->assertSame('Created', Milestone::find($m)->status->value);
        $this->assertBalances($this->client, 99.99, 0);
        Http::assertNothingSent();
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $m = $this->createMilestone(10);
        $this->postJson("/wallets/{$this->client}/fund", ['amount' => 100], $this->as($this->client));

        $this->postJson("/projects/{$this->project}/milestones/{$m}/release", [], $this->as($this->client))->assertStatus(409);
        $this->postJson("/projects/{$this->project}/milestones/{$m}/fund", [], $this->as($this->client))->assertOk();
        $this->postJson("/projects/{$this->project}/milestones/{$m}/fund", [], $this->as($this->client))->assertStatus(409);
        $this->postJson("/projects/{$this->project}/milestones/{$m}/release", [], $this->as($this->client))->assertOk();
        $this->postJson("/projects/{$this->project}/milestones/{$m}/release", [], $this->as($this->client))->assertStatus(409);

        $this->assertBalances($this->freelancer, 10, 0);
    }

    public function test_only_project_owner_can_fund_or_release(): void
    {
        $m = $this->createMilestone(10);
        $other = $this->register('client', 'other@example.com');

        $this->postJson("/projects/{$this->project}/milestones/{$m}/fund", [], $this->as($other))->assertForbidden();
        $this->postJson("/projects/{$this->project}/milestones/{$m}/release", [], $this->as($this->freelancer))->assertForbidden();
        $this->postJson("/wallets/{$this->client}/fund", ['amount' => 5], $this->as($other))->assertForbidden();
        $this->postJson("/projects/{$this->project}/milestones/{$m}/fund")->assertUnauthorized();
    }

    public function test_missing_records_return_404_and_milestones_are_scoped_to_project(): void
    {
        $m = $this->createMilestone(10);
        $otherProject = $this->postJson('/projects', ['title' => 'Q', 'freelancer_ids' => [$this->freelancer]], $this->as($this->client))->json('data.id');

        $this->getJson('/users/9999')->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $this->getJson("/projects/{$otherProject}/milestones/{$m}")->assertNotFound();
        $this->postJson("/projects/{$otherProject}/milestones/{$m}/fund", [], $this->as($this->client))->assertNotFound();
    }

    public function test_validation_errors(): void
    {
        $this->postJson('/register', ['role' => 'admin'])->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'role', 'kyc'], 'error.fields');
        $this->postJson('/projects', ['title' => 'X', 'freelancer_ids' => [$this->client]], $this->as($this->client))
            ->assertStatus(422)->assertJsonValidationErrors(['freelancer_ids'], 'error.fields');
        $this->postJson("/projects/{$this->project}/milestones", ['title' => 'X', 'amount' => 1.234, 'due_date' => '2000-01-01'], $this->as($this->client))
            ->assertStatus(422)->assertJsonValidationErrors(['milestones.0.amount', 'milestones.0.due_date'], 'error.fields');
    }

    public function test_project_details_include_milestones_freelancers_and_kyc_is_masked(): void
    {
        $this->createMilestone(10);
        $this->getJson("/projects/{$this->project}")
            ->assertOk()
            ->assertJsonCount(1, 'data.milestones')
            ->assertJsonPath('data.freelancers.0.id', $this->freelancer);

        $this->getJson("/users/{$this->client}")
            ->assertJsonPath('data.kyc.government_id_number', '*******6789')
            ->assertJsonPath('data.wallet.available_balance', 0);
    }

    private function register(string $role, string $email): int
    {
        return $this->postJson('/register', [
            'name' => ucfirst($role), 'email' => $email, 'role' => $role,
            'kyc' => [
                'legal_full_name' => "Legal $role", 'country' => 'ng',
                'government_id_type' => 'passport', 'government_id_number' => 'ID123456789',
            ],
        ])->assertCreated()->json('data.id');
    }

    private function createMilestone(float $amount): int
    {
        return $this->postJson("/projects/{$this->project}/milestones", [
            'title' => 'M', 'amount' => $amount, 'due_date' => now()->addMonth()->toDateString(),
        ], $this->as($this->client))->assertCreated()->json('data.0.id');
    }

    private function as(int $userId): array
    {
        return ['X-User-Id' => $userId];
    }

    private function assertBalances(int $userId, float $available, float $escrow): void
    {
        $wallet = $this->getJson("/users/{$userId}")->json('data.wallet');
        $this->assertEquals($available, $wallet['available_balance']);
        $this->assertEquals($escrow, $wallet['escrow_balance']);
    }
}
