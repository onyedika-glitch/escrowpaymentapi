<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['event', 'url', 'payload', 'response_status', 'success', 'error'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'success' => 'boolean'];
    }
}
