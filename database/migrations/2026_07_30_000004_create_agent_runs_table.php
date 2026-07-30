<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('queued');
            $table->string('mode')->default('autonomous');
            $table->json('contract');
            $table->unsignedInteger('step_count')->default(0);
            $table->string('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('index');
            $table->string('phase');
            $table->json('action')->nullable();
            $table->text('reasoning')->nullable();
            $table->unsignedInteger('ref')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->string('gate_decision')->nullable();
            $table->string('gate_reason')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['agent_run_id', 'index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_steps');
        Schema::dropIfExists('agent_runs');
    }
};
