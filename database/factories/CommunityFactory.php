<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Communities\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Community>
 */
final class CommunityFactory extends Factory
{
    protected $model = Community::class;

    /** @return array{name: string, slug: string, description: ?string} */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->optional()->paragraph(),
        ];
    }
}
