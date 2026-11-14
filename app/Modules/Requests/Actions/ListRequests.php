<?php

declare(strict_types=1);

namespace App\Modules\Requests\Actions;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Identity\Models\User;
use App\Modules\Requests\Data\RequestAuthorData;
use App\Modules\Requests\Data\ServiceRequestListItemData;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListRequests
{
    public function __construct(
        private CurrentCommunity $currentCommunity,
        private Gate $gate,
        private UrlGenerator $urls,
    ) {}

    /** @return LengthAwarePaginator<int, ServiceRequestListItemData> */
    public function handle(
        User $user,
        ?RequestStatus $status = null,
        int $page = 1,
    ): LengthAwarePaginator {
        $this->gate->forUser($user)->authorize(
            'viewAny',
            ServiceRequest::class,
        );
        $community = $this->currentCommunity->community();
        $isManager = $community->communityMembers()->whereBelongsTo($user)->firstOrFail()->role === CommunityRole::Manager;

        $query = ServiceRequest::query()
            ->select(['id', 'user_id', 'title', 'status', 'created_at', 'updated_at'])
            ->where('community_id', $community->id);

        if ($isManager) {
            $query->with('author:id,name');
        } else {
            $query->where('user_id', $user->id);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate(15, page: $page)
            ->withPath($this->urls->route(
                'communities.requests.index',
                ['community' => $community->slug],
            ))
            ->appends($status === null ? [] : ['status' => $status->value])
            ->through(fn (ServiceRequest $serviceRequest): ServiceRequestListItemData => new ServiceRequestListItemData(
                id: $serviceRequest->id,
                title: $serviceRequest->title,
                status: $serviceRequest->status,
                createdAt: $serviceRequest->created_at->toImmutable(),
                updatedAt: $serviceRequest->updated_at->toImmutable(),
                author: $isManager
                    ? new RequestAuthorData(
                        $serviceRequest->author->id,
                        $serviceRequest->author->name,
                    )
                    :
                    null,
                showUrl: $this->urls->route('communities.requests.show', [
                    'community' => $community->slug,
                    'request' => $serviceRequest->id,
                ]),
            ));
    }
}
