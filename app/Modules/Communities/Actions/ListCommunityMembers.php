<?php

declare(strict_types=1);

namespace App\Modules\Communities\Actions;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Communities\Data\CommunityMemberData;
use App\Modules\Communities\Models\CommunityMember;

final readonly class ListCommunityMembers
{
    public function __construct(private CurrentCommunity $currentCommunity) {}

    /** @return list<CommunityMemberData> */
    public function handle(): array
    {
        return $this->currentCommunity->community()->communityMembers()
            ->with('user:id,name,email')
            ->orderBy('user_id')
            ->get()
            ->map(fn (CommunityMember $membership): CommunityMemberData => new CommunityMemberData(
                id: $membership->user->id,
                name: $membership->user->name,
                email: $membership->user->email,
                role: $membership->role,
            ))
            ->all();
    }
}
