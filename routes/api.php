<?php

use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\ResolveActingUser;
use Illuminate\Support\Facades\Route;

// API index (so hitting the base URL in a browser is self-describing)
Route::get('/', fn () => response()->json([
    'name' => 'Milestone Escrow Payment API',
    'docs' => 'See README.md and docs/API.md',
    'auth' => 'Endpoints marked [auth] require header X-User-Id: <userId>',
    'endpoints' => [
        'POST /register',
        'GET  /users/{userId}',
        'POST /projects [auth]',
        'GET  /projects/{projectId}',
        'POST /projects/{projectId}/milestones [auth]',
        'GET  /projects/{projectId}/milestones/{milestoneId}',
        'POST /wallets/{userId}/fund [auth]',
        'POST /projects/{projectId}/milestones/{milestoneId}/fund [auth]',
        'POST /projects/{projectId}/milestones/{milestoneId}/release [auth]',
        'GET  /wallets/{userId}/history',
        'GET  /webhooks/deliveries',
        'POST /mock/webhook',
    ],
]));

// Public
Route::post('/register', [UserController::class, 'register']);
Route::get('/users/{user}', [UserController::class, 'show']);
Route::get('/projects/{project}', [ProjectController::class, 'show']);
Route::get('/projects/{project}/milestones/{milestone}', [MilestoneController::class, 'show'])->scopeBindings();
Route::get('/wallets/{user}/history', [WalletController::class, 'history']);

// Mock webhook receiver + delivery log
Route::post('/mock/webhook', [WebhookController::class, 'receive']);
Route::get('/webhooks/deliveries', [WebhookController::class, 'deliveries']);

// Actions — require X-User-Id (mock auth)
Route::middleware(ResolveActingUser::class)->group(function () {
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::post('/projects/{project}/milestones', [MilestoneController::class, 'store']);
    Route::post('/wallets/{user}/fund', [WalletController::class, 'fund']);

    Route::scopeBindings()->group(function () {
        Route::post('/projects/{project}/milestones/{milestone}/fund', [MilestoneController::class, 'fund']);
        Route::post('/projects/{project}/milestones/{milestone}/release', [MilestoneController::class, 'release']);
    });
});
