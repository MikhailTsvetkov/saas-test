<?php

declare(strict_types=1);

namespace App\Modules\Requests\Data;

use App\Modules\Requests\Enums\RequestStatus;
use Carbon\CarbonImmutable;

final readonly class ServiceRequestData
{
    /**
     * @param  list<RequestStatus>  $allowedStatuses
     * @param  list<RequestStatusHistoryData>  $statusHistories
     * @param  list<RequestMessageData>  $messages
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $description,
        public RequestStatus $status,
        public RequestAuthorData $author,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
        public ?CarbonImmutable $resolvedAt,
        public array $allowedStatuses,
        public array $statusHistories,
        public array $messages = [],
    ) {}

    /**
     * @return array{
     *     id: int, title: string, description: string, status: string,
     *     author: array{id: int, name: string},
     *     created_at: string, updated_at: string, resolved_at: ?string,
     *     allowed_statuses: list<string>,
     *     statusHistories: list<array{id: int, from_status: ?string, to_status: string, author: ?array{id: int, name: string}, created_at: string}>,
     *     messages?: list<array{id: int, body: string, author: array{id: int, name: string}, role: ?string, created_at: string}>
     * }
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'author' => $this->author->toArray(),
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
            'resolved_at' => $this->resolvedAt?->toISOString(),
            'allowed_statuses' => array_map(
                static fn (RequestStatus $status): string => $status->value,
                $this->allowedStatuses,
            ),
            'statusHistories' => array_map(
                fn (RequestStatusHistoryData $history): array => $history->toArray(),
                $this->statusHistories,
            ),
        ];

        if ($this->messages !== []) {
            $data['messages'] = array_map(
                fn (RequestMessageData $message): array => $message->toArray(),
                $this->messages,
            );
        }

        return $data;
    }
}
