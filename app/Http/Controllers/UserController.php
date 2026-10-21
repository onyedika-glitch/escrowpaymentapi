<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(Role::class)],
            'kyc' => ['required', 'array'],
            'kyc.legal_full_name' => ['required', 'string', 'max:255'],
            'kyc.country' => ['required', 'string', 'size:2', 'alpha'],
            'kyc.government_id_type' => ['required', Rule::in(['passport', 'national_id', 'drivers_license'])],
            'kyc.government_id_number' => ['required', 'string', 'min:4', 'max:64'],
            'kyc.date_of_birth' => ['nullable', 'date', 'before:-18 years'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            // KYC is simulated: any complete submission is auto-verified.
            $user->kyc()->create([
                ...$data['kyc'],
                'country' => strtoupper($data['kyc']['country']),
                'status' => 'Verified',
                'verified_at' => now(),
            ]);
            $user->wallet()->create(['currency' => config('escrow.currency')]);

            return $user;
        });

        return (new UserResource($user->load('kyc', 'wallet')))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('kyc', 'wallet'));
    }
}
