<?php

declare(strict_types=1);

namespace App\Modules\Requests\Data;

use App\Modules\Communities\Enums\CommunityRole;
use Carbon\CarbonImmutable;

final readonly class RequestMessageData
{
    public function __construct(
        public int $id,
        public string $body,
        public RequestAuthorData $author,
        public ?CommunityRole $role,
        public CarbonImmutable $createdAt,
    ) {}

    /** @return array{id: int, body: string, author: array{id: int, name: string}, role: ?string, created_at: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->author->toArray(),
            'role' => $this->role?->value,
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
