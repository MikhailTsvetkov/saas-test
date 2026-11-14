<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Communities\Enums\CommunityRole;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\ResponseFactory;

final class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly CurrentCommunity $currentCommunity,
        private readonly ResponseFactory $inertia,
        private readonly UrlGenerator $urls,
    ) {}

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => $this->inertia->always(fn (): array => [
                'user' => $request->user()?->only(['id', 'name', 'email']),
            ]),
            'community' => $this->inertia->always(fn (): ?array => $this->currentCommunity->isSet() ? [
                'id' => $this->currentCommunity->community()->id,
                'name' => $this->currentCommunity->community()->name,
                'slug' => $this->currentCommunity->community()->slug,
                'role' => $this->currentCommunity->role()->value,
            ] : null),
            'urls' => $this->inertia->always(fn (): array => [
                'login' => $this->urls->route('login'),
                'loginStore' => $this->urls->route('login.store'),
                'logout' => $this->urls->route('logout'),
                'communities' => $this->urls->route('communities.index'),
                'requests' => $this->currentCommunity->isSet()
                    ? $this->urls->route('communities.requests.index', [
                        'community' => $this->currentCommunity->community()->slug,
                    ])
                    : null,
                'dashboard' => $this->currentCommunity->isSet()
                    ? $this->urls->route(
                        'communities.dashboard',
                        ['community' => $this->currentCommunity->community()->slug],
                    )
                    :
                    null,
                'members' => $this->currentCommunity->isSet() && $this->currentCommunity->role() === CommunityRole::Manager
                    ? $this->urls->route(
                        'communities.members',
                        ['community' => $this->currentCommunity->community()->slug],
                    )
                    :
                    null,
            ]),
        ];
    }
}
