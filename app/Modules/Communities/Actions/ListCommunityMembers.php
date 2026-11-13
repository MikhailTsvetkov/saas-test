<?php

declare(strict_types=1);

namespace App\Modules\Communities\Actions;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Communities\Models\CommunityMember;

final readonly class ListCommunityMembers
{
    public function __construct(private CurrentCommunity $currentCommunity) {}

    /** @return list<array{id: int, name: string, email: string, role: string}> */
    public function handle(): array
    {
        return $this->currentCommunity->community()->communityMembers()
            ->with('user:id,name,email')
            ->orderBy('user_id')
            ->get()
            ->map(fn (CommunityMember $membership): array => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
            ])
            ->all();
    }
}
