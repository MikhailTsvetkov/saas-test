<?php

declare(strict_types=1);

namespace App\Modules\Communities;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\Community;
use App\Modules\Communities\Models\CommunityMember;
use InvalidArgumentException;
use LogicException;

final class CurrentCommunity
{
    private ?Community $community = null;

    private ?CommunityMember $membership = null;

    /** @throws InvalidArgumentException */
    public function set(Community $community, CommunityMember $membership): void
    {
        if ($membership->community_id !== $community->getKey()) {
            throw new InvalidArgumentException(
                'The membership does not belong to the current community.',
            );
        }

        $this->community = $community;
        $this->membership = $membership;
    }

    public function isSet(): bool
    {
        return $this->community !== null && $this->membership !== null;
    }

    /** @throws LogicException */
    public function community(): Community
    {
        return $this->community ?? throw new LogicException(
            'The current community has not been resolved.',
        );
    }

    /** @throws LogicException */
    public function membership(): CommunityMember
    {
        return $this->membership ?? throw new LogicException(
            'The current community has not been resolved.',
        );
    }

    /** @throws LogicException */
    public function role(): CommunityRole
    {
        return $this->membership()->role;
    }

    public function clear(): void
    {
        $this->community = null;
        $this->membership = null;
    }
}
