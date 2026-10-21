<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Exceptions\EscrowException;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $client = $request->user();
        if (! $client->isClient()) {
            throw EscrowException::forbidden('Only clients can create projects.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'freelancer_ids' => ['required', 'array', 'min:1'],
            'freelancer_ids.*' => ['integer', 'distinct'],
        ]);

        $freelancers = User::whereIn('id', $data['freelancer_ids'])->where('role', Role::Freelancer)->pluck('id');
        $invalid = array_values(array_diff($data['freelancer_ids'], $freelancers->all()));
        if ($invalid) {
            throw ValidationException::withMessages([
                'freelancer_ids' => ['These ids are not registered freelancers: '.implode(', ', $invalid)],
            ]);
        }

        $project = DB::transaction(function () use ($client, $data, $freelancers) {
            $project = $client->ownedProjects()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => 'Open',
            ]);
            $project->freelancers()->attach($freelancers);

            return $project;
        });

        return (new ProjectResource($this->loadFull($project)))->response()->setStatusCode(201);
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($this->loadFull($project));
    }

    private function loadFull(Project $project): Project
    {
        return $project->load('client', 'freelancers', 'milestones');
    }
}
