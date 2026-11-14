<?php

declare(strict_types=1);

namespace App\Modules\Requests\Actions;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Identity\Models\User;
use App\Modules\Requests\Models\RequestMessage;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

final readonly class AddRequestMessage
{
    public function __construct(
        private CurrentCommunity $currentCommunity,
        private Gate $gate,
        private ConnectionInterface $database,
    ) {}

    public function handle(
        User $user,
        ServiceRequest $serviceRequest,
        string $body,
    ): RequestMessage {
        if (
            ! $this->currentCommunity->isSet()
            || $serviceRequest->community_id !== $this->currentCommunity->community()->id
        ) {
            throw new AuthorizationException(
                'У вас нет доступа к этому обращению в текущем сообществе.',
            );
        }

        $this->gate->forUser($user)->authorize('addMessage', $serviceRequest);

        $trimmedBody = trim($body);

        return $this->database->transaction(function () use (
            $user,
            $serviceRequest,
            $trimmedBody,
        ): RequestMessage {
            $message = $serviceRequest->messages()->create([
                'user_id' => $user->id,
                'body' => $trimmedBody,
            ]);

            $serviceRequest->touch();

            return $message->fresh();
        });
    }
}
