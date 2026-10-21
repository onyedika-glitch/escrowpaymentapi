<?php

return [
    // Where milestone status-change notifications are POSTed. Point this at any
    // mock receiver (e.g. https://webhook.site/...) — defaults to the built-in /mock/webhook.
    'webhook_url' => env('ESCROW_WEBHOOK_URL', 'http://127.0.0.1:8000/mock/webhook'),
    'webhook_timeout' => (int) env('ESCROW_WEBHOOK_TIMEOUT', 3),
    'currency' => env('ESCROW_CURRENCY', 'USD'),
];
