<?php

namespace App\Services;

use App\Models\Milestone;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fires a JSON POST to config('escrow.webhook_url') on milestone status changes.
 * Delivery is best-effort: failures are logged and stored but never roll back
 * the (already committed) money movement. In production this would be a queued
 * job with retries + an HMAC signature header.
 */
class WebhookNotifier
{
    public function milestoneStatusChanged(Milestone $milestone): WebhookDelivery
    {
        $milestone->loadMissing('project.client.kyc', 'freelancer.kyc');
        $event = 'milestone.'.strtolower($milestone->status->value);
        $url = config('escrow.webhook_url');

        $payload = [
            'id' => (string) Str::uuid(),
            'event' => $event,
            'occurred_at' => now()->toIso8601String(),
            'data' => [
                'project_id' => $milestone->project_id,
                'milestone_id' => $milestone->id,
                'status' => $milestone->status->value,
                'amount' => $milestone->amount_cents / 100,
                'currency' => config('escrow.currency'),
                'client' => $milestone->project->client->kycSnapshot(),
                'freelancer' => $milestone->freelancer->kycSnapshot(),
            ],
        ];

        $delivery = new WebhookDelivery(['event' => $event, 'url' => $url, 'payload' => $payload]);

        try {
            $response = Http::timeout(config('escrow.webhook_timeout'))
                ->withHeaders(['X-Escrow-Event' => $event])
                ->post($url, $payload);
            $delivery->response_status = $response->status();
            $delivery->success = $response->successful();
        } catch (Throwable $e) {
            $delivery->error = $e->getMessage();
        }

        $delivery->save();
        Log::channel('webhooks')->info("OUTBOUND {$event} -> {$url} [".($delivery->response_status ?? 'ERR').']', $payload);

        return $delivery;
    }
}
