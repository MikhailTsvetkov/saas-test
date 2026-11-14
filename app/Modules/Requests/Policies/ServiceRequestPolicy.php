<?php

declare(strict_types=1);

namespace App\Modules\Requests\Policies;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Identity\Models\User;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Auth\Access\Response;

final readonly class ServiceRequestPolicy
{
    public function __construct(private CurrentCommunity $currentCommunity) {}

    public function viewAny(User $user): Response
    {
        return $this->membership($user) !== null
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): Response
    {
        $membership = $this->membership($user);

        if ($membership === null) {
            return Response::denyAsNotFound();
        }

        return $membership->role === CommunityRole::Resident
            ? Response::allow()
            : Response::deny('Создавать обращения могут только жители.');
    }

    public function view(User $user, ServiceRequest $serviceRequest): Response
    {
        $membership = $this->membership($user, $serviceRequest);

        if ($membership === null) {
            return Response::denyAsNotFound();
        }

        return $membership->role === CommunityRole::Manager || $serviceRequest->user_id === $user->id
            ? Response::allow()
            : Response::deny('Вы можете просматривать только свои обращения.');
    }

    public function addMessage(User $user, ServiceRequest $serviceRequest): Response
    {
        return $this->view($user, $serviceRequest);
    }

    public function changeStatus(User $user, ServiceRequest $serviceRequest): Response
    {
        $membership = $this->membership($user, $serviceRequest);

        if ($membership === null) {
            return Response::denyAsNotFound();
        }

        return $membership->role === CommunityRole::Manager
            ? Response::allow()
            : Response::deny('Изменять статус может только управляющий сообщества.');
    }

    private function membership(User $user, ?ServiceRequest $serviceRequest = null): ?CommunityMember
    {
        if (! $this->currentCommunity->isSet()
            || $this->currentCommunity->membership()->user_id !== $user->id
            || (
                $serviceRequest !== null
                &&
                $serviceRequest->community_id !== $this->currentCommunity->community()->id
            )
        ) {
            return null;
        }

        return $this->currentCommunity->community()->communityMembers()
            ->whereBelongsTo($user)->first();
    }
}
