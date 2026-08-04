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
        Schema::create('posting_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
            $table->json('days')->nullable();
            $table->string('time_of_day')->default('06:00');
            $table->string('timezone')->default('Africa/Kampala');
            $table->unsignedTinyInteger('posts_per_run')->default(1);
            $table->json('notify_emails')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_ran_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'last_ran_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posting_schedules');
    }
};
