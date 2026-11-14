<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Models\RequestStatusHistory;
use App\Modules\Requests\Models\ServiceRequest;
use Closure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestStatusHistory>
 */
final class RequestStatusHistoryFactory extends Factory
{
    protected $model = RequestStatusHistory::class;

    /** @return array{service_request_id: ServiceRequestFactory, user_id: Closure, from_status: null, to_status: RequestStatus} */
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'user_id' => fn (array $attributes): int => ServiceRequest::query()
                ->findOrFail($attributes['service_request_id'])->user_id,
            'from_status' => null,
            'to_status' => RequestStatus::New,
        ];
    }
}
