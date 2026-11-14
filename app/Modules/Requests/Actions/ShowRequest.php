<?php

declare(strict_types=1);

namespace App\Modules\Requests\Actions;

use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Identity\Models\User;
use App\Modules\Requests\Data\RequestAuthorData;
use App\Modules\Requests\Data\RequestMessageData;
use App\Modules\Requests\Data\RequestStatusHistoryData;
use App\Modules\Requests\Data\ServiceRequestData;
use App\Modules\Requests\Models\RequestMessage;
use App\Modules\Requests\Models\RequestStatusHistory;
use Illuminate\Contracts\Auth\Access\Gate;

final readonly class ShowRequest
{
    public function __construct(private FindCommunityRequest $findRequest, private Gate $gate) {}

    public function handle(User $user, string $id): ServiceRequestData
    {
        $serviceRequest = $this->findRequest->handle($id);
        $this->gate->forUser($user)->authorize('view', $serviceRequest);
        $serviceRequest->load('author:id,name');

        $histories = $serviceRequest->statusHistories()->with('author:id,name')
            ->orderBy('created_at')->orderBy('id')->get();

        $messages = $serviceRequest->messages()
            ->with('author:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $roleByUserId = CommunityMember::query()
            ->where('community_id', $serviceRequest->community_id)
            ->whereIn('user_id', $messages->pluck('user_id')->all())
            ->get()
            ->mapWithKeys(fn (CommunityMember $member): array => [$member->user_id => $member->role]);

        return new ServiceRequestData(
            id: $serviceRequest->id,
            title: $serviceRequest->title,
            description: $serviceRequest->description,
            status: $serviceRequest->status,
            author: new RequestAuthorData($serviceRequest->author->id, $serviceRequest->author->name),
            createdAt: $serviceRequest->created_at->toImmutable(),
            updatedAt: $serviceRequest->updated_at->toImmutable(),
            resolvedAt: $serviceRequest->resolved_at?->toImmutable(),
            statusHistories: $histories->map(fn (
                RequestStatusHistory $history,
            ): RequestStatusHistoryData => new RequestStatusHistoryData(
                id: $history->id,
                fromStatus: $history->from_status,
                toStatus: $history->to_status,
                author: $history->author === null
                    ? null
                    : new RequestAuthorData($history->author->id, $history->author->name),
                createdAt: $history->created_at->toImmutable(),
            ))->all(),
            messages: $messages->map(fn (
                RequestMessage $message,
            ): RequestMessageData => new RequestMessageData(
                id: $message->id,
                body: $message->body,
                author: new RequestAuthorData($message->author->id, $message->author->name),
                role: $roleByUserId->get($message->user_id),
                createdAt: $message->created_at->toImmutable(),
            ))->all(),
        );
    }
}
