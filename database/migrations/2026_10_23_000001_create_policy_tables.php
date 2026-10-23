<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('public_request_id')->unique();
            $table->string('agent_id');
            $table->string('client_request_id');
            $table->string('customer_name');
            $table->date('date_of_birth');
            $table->string('destination');
            $table->date('trip_start');
            $table->date('trip_end');
            $table->string('plan_code');
            $table->unsignedBigInteger('sum_insured');
            $table->decimal('premium_snapshot', 12, 2);
            $table->string('status')->default('RECEIVED');
            $table->string('provider_reference')->nullable();
            $table->string('provider_policy_number')->nullable()->unique();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('issue_attempt_count')->default(0);
            $table->json('last_provider_response')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'client_request_id']);
            $table->index('status');
            $table->index('provider_reference');
            $table->index(['trip_start', 'trip_end']);
        });

        Schema::create('issuance_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')->constrained('policy_requests')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('provider_reference');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['policy_request_id', 'attempt_number']);
            $table->index('provider_reference');
        });

        Schema::create('provider_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')->constrained('policy_requests')->cascadeOnDelete();
            $table->string('provider_reference');
            $table->string('provider_event_id')->unique();
            $table->string('event_type');
            $table->string('status');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['provider_reference', 'event_type']);
        });

        Schema::create('policy_state_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')->constrained('policy_requests')->cascadeOnDelete();
            $table->string('from_state');
            $table->string('to_state');
            $table->string('source');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['policy_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_state_history');
        Schema::dropIfExists('provider_events');
        Schema::dropIfExists('issuance_attempts');
        Schema::dropIfExists('policy_requests');
    }
};
