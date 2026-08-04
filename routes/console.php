<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('agent:prune-screenshots')->dailyAt('03:00');

/**
 * Every fifteen minutes, so a cadence set to 06:00 means 06:00 rather than some
 * time in the six o'clock hour. The command decides for itself which schedules
 * are due, in each schedule's own timezone.
 */
Schedule::command('agent:draft-scheduled')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->description('Draft posts for teams whose cadence is due');
