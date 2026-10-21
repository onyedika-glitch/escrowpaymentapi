<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Milestone */
class MilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'amount' => Money::format($this->amount_cents),
            'currency' => config('escrow.currency'),
            'due_date' => $this->due_date->toDateString(),
            'status' => $this->status->value,
            'funded_at' => $this->funded_at,
            'released_at' => $this->released_at,
            'freelancer_id' => $this->freelancer_id,
            'involved_users' => $this->when(
                $this->relationLoaded('freelancer') && $this->relationLoaded('project'),
                fn () => [
                    'client' => new UserSummaryResource($this->project->client),
                    'freelancer' => new UserSummaryResource($this->freelancer),
                ],
            ),
            'created_at' => $this->created_at,
        ];
    }
}
