<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\Community;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Identity\Models\User;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
final class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    /** @return array{community_id: CommunityFactory, user_id: UserFactory, title: string, description: string, status: RequestStatus, resolved_at: null} */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => RequestStatus::New,
            'resolved_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ServiceRequest $serviceRequest): void {
            CommunityMember::query()->firstOrCreate([
                'community_id' => $serviceRequest->community_id,
                'user_id' => $serviceRequest->user_id,
            ], [
                'role' => CommunityRole::Resident,
            ]);
        });
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => RequestStatus::InProgress,
            'resolved_at' => null,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => RequestStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }
}
