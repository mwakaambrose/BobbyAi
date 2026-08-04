<?php

use App\Http\Controllers\Agent\BrandProfileController;
use App\Http\Controllers\Agent\PostAlternativeController;
use App\Http\Controllers\Agent\PostApprovalController;
use App\Http\Controllers\Agent\PostController;
use App\Http\Controllers\Agent\PostMessageController;
use App\Http\Controllers\Agent\RunController;
use App\Http\Controllers\Agent\ScreenshotController;
use App\Http\Controllers\Agent\SocialAccountController;
use App\Http\Controllers\Agent\StudioController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', HomeController::class)->name('dashboard');

        Route::get('studio', StudioController::class)->name('studio');

        Route::post('posts', [PostController::class, 'store'])->name('posts.store');
        Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');
        Route::post('posts/{post}/messages', [PostMessageController::class, 'store'])->name('posts.messages.store');
        Route::post('posts/{post}/alternatives', [PostAlternativeController::class, 'store'])->name('posts.alternatives.store');
        Route::post('posts/{post}/approve', [PostApprovalController::class, 'store'])->name('posts.approve');

        Route::get('runs', [RunController::class, 'index'])->name('runs.index');
        Route::get('runs/{run}', [RunController::class, 'show'])->name('runs.show');
        Route::post('runs/{run}/abort', [RunController::class, 'abort'])->name('runs.abort');
        Route::get('runs/{run}/steps/{step}/screenshot', ScreenshotController::class)->name('runs.screenshot');

        Route::get('accounts', [SocialAccountController::class, 'index'])->name('accounts.index');
        Route::post('accounts/{account}/connect', [SocialAccountController::class, 'connect'])->name('accounts.connect');
        Route::post('accounts/{account}/verify', [SocialAccountController::class, 'verify'])->name('accounts.verify');

        Route::get('brand', [BrandProfileController::class, 'edit'])->name('brand.edit');
        Route::put('brand', [BrandProfileController::class, 'update'])->name('brand.update');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
