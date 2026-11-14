<?php

declare(strict_types=1);

namespace App\Modules\Communities\Data;

use App\Modules\Communities\Enums\CommunityRole;

final readonly class CommunityMemberData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public CommunityRole $role,
    ) {}

    /** @return array{id: int, name: string, email: string, role: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
        ];
    }
}
