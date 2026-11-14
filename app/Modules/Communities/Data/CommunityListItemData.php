<?php

declare(strict_types=1);

namespace App\Modules\Communities\Data;

use App\Modules\Communities\Enums\CommunityRole;

final readonly class CommunityListItemData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public CommunityRole $role,
        public string $dashboardUrl,
    ) {}

    /** @return array{id: int, name: string, slug: string, description: ?string, role: string, dashboardUrl: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'role' => $this->role->value,
            'dashboardUrl' => $this->dashboardUrl,
        ];
    }
}
