<?php

namespace App\Http\Controllers;

use App\Exceptions\EscrowException;
use App\Http\Resources\MilestoneResource;
use App\Models\Milestone;
use App\Models\Project;
use App\Services\EscrowService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MilestoneController extends Controller
{
    public function __construct(private readonly EscrowService $escrow) {}

    /**
     * Accepts either a single milestone object or {"milestones": [ ... ]} for bulk creation.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        if ($project->client_id !== $request->user()->id) {
            throw EscrowException::forbidden('Only the project owner can add milestones.');
        }

        $items = $request->has('milestones') ? $request->input('milestones') : [$request->all()];

        $validated = validator(['milestones' => $items], [
            'milestones' => ['required', 'array', 'min:1', 'max:50'],
            'milestones.*.title' => ['required', 'string', 'max:255'],
            'milestones.*.description' => ['nullable', 'string', 'max:5000'],
            'milestones.*.amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:10000000'],
            'milestones.*.due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'milestones.*.freelancer_id' => ['nullable', 'integer'],
        ])->validate()['milestones'];

        $assigned = $project->freelancers()->pluck('users.id');

        $milestones = DB::transaction(fn () => collect($validated)->map(function (array $m, int $i) use ($project, $assigned) {
            // Default to the sole assigned freelancer; require an explicit choice otherwise.
            $freelancerId = $m['freelancer_id'] ?? ($assigned->count() === 1 ? $assigned->first() : null);
            if (! $freelancerId || ! $assigned->contains($freelancerId)) {
                throw ValidationException::withMessages([
                    "milestones.$i.freelancer_id" => ['Must be one of the freelancers assigned to this project: '.$assigned->implode(', ')],
                ]);
            }

            return $project->milestones()->create([
                'freelancer_id' => $freelancerId,
                'title' => $m['title'],
                'description' => $m['description'] ?? null,
                'amount_cents' => Money::toCents($m['amount']),
                'due_date' => $m['due_date'],
            ])->refresh();
        }));

        return response()->json(['data' => MilestoneResource::collection($milestones)], 201);
    }

    public function show(Project $project, Milestone $milestone): MilestoneResource
    {
        return new MilestoneResource($milestone->load('project.client', 'freelancer'));
    }

    public function fund(Request $request, Project $project, Milestone $milestone): MilestoneResource
    {
        $milestone = $this->escrow->fundMilestone($milestone, $request->user());

        return new MilestoneResource($milestone->load('project.client', 'freelancer'));
    }

    public function release(Request $request, Project $project, Milestone $milestone): MilestoneResource
    {
        $milestone = $this->escrow->releaseMilestone($milestone, $request->user());

        return new MilestoneResource($milestone->load('project.client', 'freelancer'));
    }
}
