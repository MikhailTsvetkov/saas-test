<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\AuthenticateUser;
use App\Modules\Identity\Actions\LogoutUser;
use App\Modules\Identity\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(
        LoginRequest $request,
        AuthenticateUser $authenticate,
    ): RedirectResponse {
        $authenticate->handle(
            $request->validated('email'),
            $request->validated('password'),
        );

        return to_route('home');
    }

    public function destroy(LogoutUser $logout): RedirectResponse
    {
        $logout->handle();

        return to_route('login');
    }
}
