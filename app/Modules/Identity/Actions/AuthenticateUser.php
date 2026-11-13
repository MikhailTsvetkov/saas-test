<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final readonly class AuthenticateUser
{
    public function __construct(private Session $session) {}

    /** @throws ValidationException */
    public function handle(string $email, string $password): void
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            throw ValidationException::withMessages([
                'email' => 'Не удалось войти. Проверьте email и пароль.',
            ]);
        }

        $this->session->regenerate();
    }
}
