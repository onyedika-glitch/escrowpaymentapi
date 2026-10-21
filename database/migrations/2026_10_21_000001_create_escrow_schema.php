<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core escrow schema. All money is stored as integer minor units (cents)
 * to avoid floating-point rounding errors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role', 20); // client | freelancer
            $table->timestamps();
        });

        Schema::create('kyc_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('legal_full_name');
            $table->char('country', 2); // ISO 3166-1 alpha-2
            $table->string('government_id_type', 30); // passport | national_id | drivers_license
            $table->string('government_id_number');
            $table->date('date_of_birth')->nullable();
            $table->string('status', 20)->default('Verified'); // simulated KYC check
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance_cents')->default(0); // available to spend
            $table->bigInteger('escrow_cents')->default(0);  // locked in funded milestones
            $table->char('currency', 3)->default('USD');
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('Open');
            $table->timestamps();
        });

        Schema::create('project_freelancer', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users');
            $table->timestamp('assigned_at')->useCurrent();
            $table->primary(['project_id', 'freelancer_id']);
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->bigInteger('amount_cents');
            $table->date('due_date');
            $table->string('status', 20)->default('Created'); // Created -> Funded -> Released
            $table->timestamp('funded_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });

        // Append-only ledger: every balance movement is recorded with the resulting balance.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained();
            $table->string('type', 40); // see App\Enums\LedgerType
            $table->string('direction', 6); // credit | debit (relative to available balance; 'hold' moves to escrow)
            $table->bigInteger('amount_cents');
            $table->bigInteger('balance_after_cents');
            $table->bigInteger('escrow_after_cents');
            $table->foreignId('project_id')->nullable()->constrained();
            $table->foreignId('milestone_id')->nullable()->constrained();
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['wallet_id', 'created_at']);
        });

        // Outbound webhook audit log (doubles as the demo "webhook output").
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40);
            $table->string('url');
            $table->json('payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->boolean('success')->default(false);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('project_freelancer');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('kyc_profiles');
        Schema::dropIfExists('users');
    }
};
