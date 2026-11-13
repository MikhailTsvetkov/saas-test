<?php

declare(strict_types=1);

namespace App\Modules\Communities\Http\Middleware;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Communities\Models\Community;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveCommunity
{
    public function __construct(private CurrentCommunity $currentCommunity) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $this->currentCommunity->clear();

        try {
            $user = $request->user();

            if ($user === null) {
                throw new AuthenticationException;
            }

            $community = $request->route('community');

            if (! $community instanceof Community) {
                throw new LogicException(
                    'ResolveCommunity requires a route-bound Community.',
                );
            }

            $membership = $community->communityMembers()
                ->where('user_id', $user->getAuthIdentifier())
                ->firstOrFail();

            $this->currentCommunity->set($community, $membership);

            return $next($request);
        } finally {
            $this->currentCommunity->clear();
        }
    }
}
