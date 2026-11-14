<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Requests\Models\RequestMessage;
use App\Modules\Requests\Models\ServiceRequest;
use Closure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestMessage>
 */
final class RequestMessageFactory extends Factory
{
    protected $model = RequestMessage::class;

    /** @return array{service_request_id: ServiceRequestFactory, user_id: Closure, body: string} */
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'user_id' => fn (array $attributes): int => ServiceRequest::query()
                ->findOrFail($attributes['service_request_id'])->user_id,
            'body' => fake()->paragraph(),
        ];
    }
}
