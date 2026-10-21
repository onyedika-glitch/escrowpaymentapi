<?php

namespace App\Http\Controllers;

use App\Exceptions\EscrowException;
use App\Http\Resources\LedgerEntryResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\EscrowService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private readonly EscrowService $escrow) {}

    public function fund(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id !== $user->id) {
            throw EscrowException::forbidden('You can only fund your own wallet.');
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:10000000'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $entry = $this->escrow->fundWallet($user, Money::toCents($data['amount']), $data['reference'] ?? null);

        return response()->json([
            'data' => [
                'transaction' => new LedgerEntryResource($entry),
                'user' => new UserResource($user->load('wallet')),
            ],
        ], 201);
    }

    public function history(User $user): JsonResponse
    {
        $wallet = $user->wallet;

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'currency' => $wallet->currency,
                'available_balance' => Money::format($wallet->balance_cents),
                'escrow_balance' => Money::format($wallet->escrow_cents),
                'entries' => LedgerEntryResource::collection($wallet->entries()->orderBy('id')->get()),
            ],
        ]);
    }
}
