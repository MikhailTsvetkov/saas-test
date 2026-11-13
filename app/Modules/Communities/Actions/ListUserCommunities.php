<?php

declare(strict_types=1);

namespace App\Modules\Communities\Actions;

use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Routing\UrlGenerator;

final readonly class ListUserCommunities
{
    public function __construct(private UrlGenerator $urls) {}

    /** @return list<array{id: int, name: string, slug: string, description: ?string, role: string, dashboardUrl: string}> */
    public function handle(User $user): array
    {
        return $user->communityMembers()
            ->with('community:id,name,slug,description')
            ->orderBy('community_id')
            ->get()
            ->map(fn (CommunityMember $membership): array => [
                'id' => $membership->community->id,
                'name' => $membership->community->name,
                'slug' => $membership->community->slug,
                'description' => $membership->community->description,
                'role' => $membership->role->value,
                'dashboardUrl' => $this->urls->route(
                    'communities.dashboard',
                    ['community' => $membership->community->slug],
                ),
            ])
            ->all();
    }
}
