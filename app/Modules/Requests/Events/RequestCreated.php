<?php

declare(strict_types=1);

namespace App\Modules\Requests\Events;

use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RequestCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ServiceRequest $serviceRequest) {}
}
