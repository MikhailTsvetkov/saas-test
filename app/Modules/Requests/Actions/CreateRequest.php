<?php

declare(strict_types=1);

namespace App\Modules\Requests\Actions;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Identity\Models\User;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

final readonly class CreateRequest
{
    public function __construct(
        private CurrentCommunity $currentCommunity,
        private Gate $gate,
        private ConnectionInterface $database,
    ) {}

    public function handle(
        User $user,
        string $title,
        string $description,
    ): ServiceRequest {
        $this->gate->forUser($user)->authorize(
            'create',
            ServiceRequest::class,
        );

        return $this->database->transaction(function () use (
            $user,
            $title,
            $description,
        ): ServiceRequest {
            $serviceRequest = ServiceRequest::query()->create([
                'community_id' => $this->currentCommunity->community()->id,
                'user_id' => $user->id,
                'title' => $title,
                'description' => $description,
                'status' => RequestStatus::New,
            ]);

            $serviceRequest->statusHistories()->create([
                'user_id' => $user->id,
                'from_status' => null,
                'to_status' => RequestStatus::New,
            ]);

            return $serviceRequest;
        });
    }
}
