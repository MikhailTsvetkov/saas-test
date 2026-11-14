<?php

declare(strict_types=1);

namespace App\Modules\Requests\Data;

use App\Modules\Requests\Enums\RequestStatus;
use Carbon\CarbonImmutable;

final readonly class ServiceRequestListItemData
{
    public function __construct(
        public int $id,
        public string $title,
        public RequestStatus $status,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
        public ?RequestAuthorData $author,
        public string $showUrl,
    ) {}

    /** @return array{id: int, title: string, status: string, created_at: string, updated_at: string, author: ?array{id: int, name: string}, showUrl: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status->value,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
            'author' => $this->author?->toArray(),
            'showUrl' => $this->showUrl,
        ];
    }
}
