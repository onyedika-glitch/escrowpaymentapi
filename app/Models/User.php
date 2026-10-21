<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Model
{
    protected $fillable = ['name', 'email', 'role'];

    protected function casts(): array
    {
        return ['role' => Role::class];
    }

    public function kyc(): HasOne
    {
        return $this->hasOne(KycProfile::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'client_id');
    }

    public function assignedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_freelancer', 'freelancer_id');
    }

    public function isClient(): bool
    {
        return $this->role === Role::Client;
    }

    /** Point-in-time identity snapshot embedded in webhook payloads (ID number masked). */
    public function kycSnapshot(): array
    {
        $kyc = $this->kyc;

        return [
            'user_id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'kyc' => $kyc ? [
                'legal_full_name' => $kyc->legal_full_name,
                'country' => $kyc->country,
                'government_id_type' => $kyc->government_id_type,
                'government_id_number' => $kyc->maskedIdNumber(),
                'status' => $kyc->status,
            ] : null,
        ];
    }
}
