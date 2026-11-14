<?php

declare(strict_types=1);

use App\Modules\Communities\Http\Controllers\CommunityController;
use App\Modules\Communities\Http\Controllers\CommunityDashboardController;
use App\Modules\Communities\Http\Controllers\CommunityMemberController;
use App\Modules\Identity\Http\Controllers\AuthenticatedSessionController;
use App\Modules\Requests\Http\Controllers\RequestController;
use App\Modules\Requests\Http\Controllers\RequestMessageController;
use App\Modules\Requests\Http\Controllers\RequestStatusController;
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
        Route::get('/requests', [RequestController::class, 'index'])->name('communities.requests.index');
        Route::get('/requests/create', [RequestController::class, 'create'])->name('communities.requests.create');
        Route::post('/requests', [RequestController::class, 'store'])->name('communities.requests.store');
        Route::post('/requests/{request}/messages', [RequestMessageController::class, 'store'])
            ->whereNumber('request')->name('communities.requests.messages.store');
        Route::patch('/requests/{request}/status', [RequestStatusController::class, 'update'])
            ->whereNumber('request')->name('communities.requests.status.update');
        Route::get('/requests/{request}', [RequestController::class, 'show'])
            ->whereNumber('request')->name('communities.requests.show');
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
