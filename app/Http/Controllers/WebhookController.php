<?php

namespace App\Http\Controllers;

use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /** Built-in mock receiver (the default ESCROW_WEBHOOK_URL). Logs whatever it receives. */
    public function receive(Request $request): JsonResponse
    {
        Log::channel('webhooks')->info('MOCK RECEIVER got '.$request->header('X-Escrow-Event'), $request->all());

        return response()->json(['received' => true]);
    }

    /** Audit log of outbound webhook attempts — handy for demoing payloads. */
    public function deliveries(): JsonResponse
    {
        return response()->json(['data' => WebhookDelivery::latest('id')->limit(50)->get()]);
    }
}
