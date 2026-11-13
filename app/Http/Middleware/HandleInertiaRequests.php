<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => fn (): ?array => $request->user()?->only(['id', 'name', 'email']),
            ],
            'community' => null,
            'urls' => [
                'login' => route('login'),
                'loginStore' => route('login.store'),
                'logout' => route('logout'),
            ],
        ];
    }
}
