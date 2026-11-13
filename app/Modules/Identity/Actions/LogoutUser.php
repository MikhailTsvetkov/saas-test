<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

final readonly class LogoutUser
{
    public function __construct(private Session $session) {}

    public function handle(): void
    {
        Auth::logout();
        $this->session->invalidate();
        $this->session->regenerateToken();
    }
}
