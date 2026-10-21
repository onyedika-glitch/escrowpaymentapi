<?php

namespace App\Models;

use App\Enums\LedgerType;
use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'wallet_id', 'type', 'direction', 'amount_cents', 'balance_after_cents', 'escrow_after_cents',
        'project_id', 'milestone_id', 'reference', 'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerType::class,
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'escrow_after_cents' => 'integer',
        ];
    }
}
