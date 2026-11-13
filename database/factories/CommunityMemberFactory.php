<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\Community;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityMember>
 */
final class CommunityMemberFactory extends Factory
{
    protected $model = CommunityMember::class;

    /**
     * @return array{community_id: CommunityFactory, user_id: UserFactory, role: CommunityRole}
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'user_id' => User::factory(),
            'role' => CommunityRole::Resident,
        ];
    }

    public function resident(): static
    {
        return $this->state(fn (): array => ['role' => CommunityRole::Resident]);
    }

    public function manager(): static
    {
        return $this->state(fn (): array => ['role' => CommunityRole::Manager]);
    }
}
