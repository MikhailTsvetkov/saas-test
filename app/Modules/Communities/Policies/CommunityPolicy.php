<?php

declare(strict_types=1);

namespace App\Modules\Communities\Policies;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\Community;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

final readonly class CommunityPolicy
{
    public function view(User $user, Community $community): Response
    {
        return $community->communityMembers()->whereBelongsTo($user)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function viewMembers(User $user, Community $community): Response
    {
        $membership = $community->communityMembers()->whereBelongsTo($user)->first();

        if ($membership === null) {
            return Response::denyAsNotFound();
        }

        return $membership->role === CommunityRole::Manager
            ? Response::allow()
            : Response::deny('Просматривать участников может только управляющий сообщества.');
    }
}
