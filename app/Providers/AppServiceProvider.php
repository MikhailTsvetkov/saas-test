<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $email = $request->input('email');
            $key = (is_string($email) ? Str::lower($email) : '') . '|' . $request->ip();

            return Limit::perMinute(5)
                ->by($key)
                ->response(fn (Request $request, array $headers): RedirectResponse => to_route('login')
                    ->withErrors(['email' => 'Слишком много попыток входа. Подождите минуту и попробуйте снова.'])
                    ->withInput($request->only('email'))
                    ->withHeaders($headers));
        });
    }
}
