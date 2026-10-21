<?php

namespace App\Http\Resources;

use App\Enums\MilestoneStatus;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Project */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'client' => new UserSummaryResource($this->whenLoaded('client')),
            'freelancers' => UserSummaryResource::collection($this->whenLoaded('freelancers')),
            'milestones' => MilestoneResource::collection($this->whenLoaded('milestones')),
            'totals' => $this->whenLoaded('milestones', fn () => [
                'milestone_count' => $this->milestones->count(),
                'total_amount' => Money::format($this->milestones->sum('amount_cents')),
                'funded_in_escrow' => Money::format($this->milestones->where('status', MilestoneStatus::Funded)->sum('amount_cents')),
                'released' => Money::format($this->milestones->where('status', MilestoneStatus::Released)->sum('amount_cents')),
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
