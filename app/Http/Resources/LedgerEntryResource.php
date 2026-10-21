<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LedgerEntry */
class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'direction' => $this->direction,
            'amount' => Money::format($this->amount_cents),
            'available_balance_after' => Money::format($this->balance_after_cents),
            'escrow_balance_after' => Money::format($this->escrow_after_cents),
            'project_id' => $this->project_id,
            'milestone_id' => $this->milestone_id,
            'reference' => $this->reference,
            'description' => $this->description,
            'timestamp' => $this->created_at,
        ];
    }
}
