<?php

declare(strict_types=1);

use App\Modules\Communities\Http\Controllers\CommunityController;
use App\Modules\Communities\Http\Controllers\CommunityDashboardController;
use App\Modules\Communities\Http\Controllers\CommunityMemberController;
use App\Modules\Identity\Http\Controllers\AuthenticatedSessionController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Request $request): RedirectResponse => to_route(
    $request->user() === null ? 'login' : 'communities.index',
))->name('home');

Route::middleware('auth')->group(function (): void {
    Route::get('/communities', [CommunityController::class, 'index'])->name('communities.index');

    Route::prefix('/communities/{community:slug}')->middleware('community')->group(function (): void {
        Route::get('/dashboard', [CommunityDashboardController::class, 'show'])
            ->can('view', 'community')->name('communities.dashboard');
        Route::get('/members', [CommunityMemberController::class, 'index'])
            ->can('viewMembers', 'community')->name('communities.members');
    });
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
