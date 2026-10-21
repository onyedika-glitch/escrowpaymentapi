<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Enums\MilestoneStatus;
use App\Exceptions\EscrowException;
use App\Models\LedgerEntry;
use App\Models\Milestone;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

/**
 * All balance-changing operations. Each runs in a DB transaction with row locks
 * (SELECT ... FOR UPDATE) on the wallet(s) and milestone, so concurrent requests
 * cannot double-fund, double-release, or overdraw a wallet.
 *
 * Money flow:
 *   fund wallet     client.available += amt
 *   fund milestone  client.available -= amt, client.escrow += amt    (Created -> Funded)
 *   release         client.escrow    -= amt, freelancer.available += amt (Funded -> Released)
 */
class EscrowService
{
    public function __construct(private readonly WebhookNotifier $webhooks) {}

    public function fundWallet(User $client, int $amountCents, ?string $reference = null): LedgerEntry
    {
        if (! $client->isClient()) {
            throw EscrowException::forbidden('Only clients can fund an escrow wallet.');
        }

        return DB::transaction(function () use ($client, $amountCents, $reference) {
            $wallet = $this->lockWallet($client->id);
            $wallet->balance_cents += $amountCents;
            $wallet->save();

            return $this->record($wallet, LedgerType::WalletFunded, 'credit', $amountCents, [
                'reference' => $reference,
                'description' => 'Simulated deposit into escrow wallet',
            ]);
        });
    }

    public function fundMilestone(Milestone $milestone, User $actor): Milestone
    {
        $this->assertProjectOwner($milestone, $actor);

        $milestone = DB::transaction(function () use ($milestone) {
            $milestone = Milestone::lockForUpdate()->findOrFail($milestone->id);
            $this->assertTransition($milestone, MilestoneStatus::Funded);

            $wallet = $this->lockWallet($milestone->project->client_id);
            if ($wallet->balance_cents < $milestone->amount_cents) {
                throw EscrowException::insufficientFunds($wallet->balance_cents, $milestone->amount_cents);
            }

            $wallet->balance_cents -= $milestone->amount_cents;
            $wallet->escrow_cents += $milestone->amount_cents;
            $wallet->save();

            $milestone->update(['status' => MilestoneStatus::Funded, 'funded_at' => now()]);

            $this->record($wallet, LedgerType::MilestoneFunded, 'hold', $milestone->amount_cents, [
                'project_id' => $milestone->project_id,
                'milestone_id' => $milestone->id,
                'description' => "Funds moved into escrow for milestone \"{$milestone->title}\"",
            ]);

            return $milestone;
        });

        // Notify only after the transaction has committed.
        $this->webhooks->milestoneStatusChanged($milestone);

        return $milestone;
    }

    public function releaseMilestone(Milestone $milestone, User $actor): Milestone
    {
        $this->assertProjectOwner($milestone, $actor);

        $milestone = DB::transaction(function () use ($milestone) {
            $milestone = Milestone::lockForUpdate()->findOrFail($milestone->id);
            $this->assertTransition($milestone, MilestoneStatus::Released);

            // Lock both wallets in a stable order (by id) to avoid deadlocks.
            $wallets = Wallet::whereIn('user_id', [$milestone->project->client_id, $milestone->freelancer_id])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('user_id');
            $clientWallet = $wallets[$milestone->project->client_id];
            $freelancerWallet = $wallets[$milestone->freelancer_id];

            $clientWallet->escrow_cents -= $milestone->amount_cents;
            $clientWallet->save();
            $freelancerWallet->balance_cents += $milestone->amount_cents;
            $freelancerWallet->save();

            $milestone->update(['status' => MilestoneStatus::Released, 'released_at' => now()]);

            $meta = ['project_id' => $milestone->project_id, 'milestone_id' => $milestone->id];
            $this->record($clientWallet, LedgerType::MilestoneReleased, 'debit', $milestone->amount_cents, $meta + [
                'description' => "Escrow released to freelancer #{$milestone->freelancer_id} for \"{$milestone->title}\"",
            ]);
            $this->record($freelancerWallet, LedgerType::PaymentReceived, 'credit', $milestone->amount_cents, $meta + [
                'description' => "Payment received for milestone \"{$milestone->title}\"",
            ]);

            return $milestone;
        });

        $this->webhooks->milestoneStatusChanged($milestone);

        return $milestone;
    }

    private function assertProjectOwner(Milestone $milestone, User $actor): void
    {
        if ($milestone->project->client_id !== $actor->id) {
            throw EscrowException::forbidden('Only the project owner can perform this action.');
        }
    }

    private function assertTransition(Milestone $milestone, MilestoneStatus $to): void
    {
        $allowed = match ($milestone->status) {
            MilestoneStatus::Created => MilestoneStatus::Funded,
            MilestoneStatus::Funded => MilestoneStatus::Released,
            MilestoneStatus::Released => null,
        };

        if ($allowed !== $to) {
            throw EscrowException::invalidTransition($milestone->status->value, $to->value);
        }
    }

    private function lockWallet(int $userId): Wallet
    {
        return Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();
    }

    private function record(Wallet $wallet, LedgerType $type, string $direction, int $amountCents, array $attrs = []): LedgerEntry
    {
        return LedgerEntry::create($attrs + [
            'wallet_id' => $wallet->id,
            'type' => $type,
            'direction' => $direction,
            'amount_cents' => $amountCents,
            'balance_after_cents' => $wallet->balance_cents,
            'escrow_after_cents' => $wallet->escrow_cents,
        ]);
    }
}
