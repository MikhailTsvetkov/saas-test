<?php

declare(strict_types=1);

namespace App\Modules\Requests\Actions;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Identity\Models\User;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

final readonly class ChangeRequestStatus
{
    public function __construct(
        private CurrentCommunity $currentCommunity,
        private Gate $gate,
        private ConnectionInterface $database,
    ) {}

    public function handle(
        User $user,
        ServiceRequest $serviceRequest,
        string|RequestStatus $status,
    ): ServiceRequest {
        if (
            ! $this->currentCommunity->isSet()
            || $serviceRequest->community_id !== $this->currentCommunity->community()->id
        ) {
            throw new AuthorizationException(
                'У вас нет доступа к этому обращению в текущем сообществе.',
            );
        }

        $this->gate->forUser($user)->authorize('changeStatus', $serviceRequest);

        $requestStatus = $status instanceof RequestStatus
            ? $status
            : RequestStatus::tryFrom($status);

        if ($requestStatus === null) {
            throw ValidationException::withMessages([
                'status' => ['Недопустимый статус обращения.'],
            ]);
        }

        $currentStatus = $serviceRequest->status;

        if ($requestStatus === $currentStatus) {
            throw ValidationException::withMessages([
                'status' => ['Статус уже установлен в это значение.'],
            ]);
        }

        if (! in_array($requestStatus, $currentStatus->allowedNextStatuses(), true)) {
            throw ValidationException::withMessages([
                'status' => ['Запрещённый переход статуса обращения.'],
            ]);
        }

        return $this->database->transaction(function () use (
            $serviceRequest,
            $requestStatus,
            $user,
        ): ServiceRequest {
            $lockedRequest = ServiceRequest::query()
                ->whereKey($serviceRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedStatus = $lockedRequest->status;
            $nextStatus = $requestStatus;

            if ($nextStatus === $lockedStatus) {
                throw ValidationException::withMessages([
                    'status' => ['Статус уже установлен в это значение.'],
                ]);
            }

            if (! in_array($nextStatus, $lockedStatus->allowedNextStatuses(), true)) {
                throw ValidationException::withMessages([
                    'status' => ['Запрещённый переход статуса обращения.'],
                ]);
            }

            $lockedRequest->fill([
                'status' => $nextStatus,
                'resolved_at' => $nextStatus === RequestStatus::Resolved ? now() : null,
            ]);

            $lockedRequest->statusHistories()->create([
                'user_id' => $user->id,
                'from_status' => $lockedStatus,
                'to_status' => $nextStatus,
            ]);

            $lockedRequest->save();

            return $lockedRequest->fresh();
        });
    }
}
