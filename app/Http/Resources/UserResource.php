<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'kyc' => $this->whenLoaded('kyc', fn () => $this->kyc ? [
                'legal_full_name' => $this->kyc->legal_full_name,
                'country' => $this->kyc->country,
                'government_id_type' => $this->kyc->government_id_type,
                'government_id_number' => $this->kyc->maskedIdNumber(),
                'date_of_birth' => $this->kyc->date_of_birth?->toDateString(),
                'status' => $this->kyc->status,
                'verified_at' => $this->kyc->verified_at,
            ] : null),
            'wallet' => $this->whenLoaded('wallet', fn () => [
                'currency' => $this->wallet->currency,
                'available_balance' => Money::format($this->wallet->balance_cents),
                'escrow_balance' => Money::format($this->wallet->escrow_cents),
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
