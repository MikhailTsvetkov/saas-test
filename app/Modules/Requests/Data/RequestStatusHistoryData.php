<?php

declare(strict_types=1);

namespace App\Modules\Requests\Data;

use App\Modules\Requests\Enums\RequestStatus;
use Carbon\CarbonImmutable;

final readonly class RequestStatusHistoryData
{
    public function __construct(
        public int $id,
        public ?RequestStatus $fromStatus,
        public RequestStatus $toStatus,
        public ?RequestAuthorData $author,
        public CarbonImmutable $createdAt,
    ) {}

    /** @return array{id: int, from_status: ?string, to_status: string, author: ?array{id: int, name: string}, created_at: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->fromStatus?->value,
            'to_status' => $this->toStatus->value,
            'author' => $this->author?->toArray(),
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
