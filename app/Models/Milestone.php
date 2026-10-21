<?php

namespace App\Models;

use App\Enums\MilestoneStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Milestone extends Model
{
    protected $fillable = [
        'project_id', 'freelancer_id', 'title', 'description', 'amount_cents', 'due_date',
        'status', 'funded_at', 'released_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MilestoneStatus::class,
            'amount_cents' => 'integer',
            'due_date' => 'date:Y-m-d',
            'funded_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }
}
