<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycProfile extends Model
{
    protected $fillable = [
        'legal_full_name', 'country', 'government_id_type', 'government_id_number',
        'date_of_birth', 'status', 'verified_at',
    ];

    // Raw government ID never leaves the service in API responses.
    protected $hidden = ['government_id_number'];

    protected function casts(): array
    {
        return [
            'government_id_number' => 'encrypted',
            'date_of_birth' => 'date:Y-m-d',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maskedIdNumber(): string
    {
        $id = (string) $this->government_id_number;

        return str_repeat('*', max(0, strlen($id) - 4)).substr($id, -4);
    }
}
