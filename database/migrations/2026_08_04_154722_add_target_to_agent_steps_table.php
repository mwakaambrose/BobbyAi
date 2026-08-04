<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A step records the numeric ref it aimed at, but not what that ref pointed
     * to, and the observation that could resolve it is gone by the time the run
     * is replayed. Without the name a trace can only say "element 12".
     */
    public function up(): void
    {
        Schema::table('agent_steps', function (Blueprint $table): void {
            $table->string('target')->nullable()->after('ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_steps', function (Blueprint $table): void {
            $table->dropColumn('target');
        });
    }
};
