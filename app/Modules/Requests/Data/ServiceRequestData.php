<?php

declare(strict_types=1);

namespace App\Modules\Requests\Data;

use App\Modules\Requests\Enums\RequestStatus;
use Carbon\CarbonImmutable;

final readonly class ServiceRequestData
{
    /** @param list<RequestStatusHistoryData> $statusHistories */
    public function __construct(
        public int $id,
        public string $title,
        public string $description,
        public RequestStatus $status,
        public RequestAuthorData $author,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
        public ?CarbonImmutable $resolvedAt,
        public array $statusHistories,
    ) {}

    /**
     * @return array{
     *     id: int, title: string, description: string, status: string,
     *     author: array{id: int, name: string},
     *     created_at: string, updated_at: string, resolved_at: ?string,
     *     statusHistories: list<array{id: int, from_status: ?string, to_status: string, author: ?array{id: int, name: string}, created_at: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'author' => $this->author->toArray(),
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
            'resolved_at' => $this->resolvedAt?->toISOString(),
            'statusHistories' => array_map(
                fn (RequestStatusHistoryData $history): array => $history->toArray(),
                $this->statusHistories,
            ),
        ];
    }
}
