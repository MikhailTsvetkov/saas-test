<?php

declare(strict_types=1);

namespace App\Modules\Requests\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Requests\Enums\RequestStatus;
use Database\Factories\RequestStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_request_id', 'user_id', 'from_status', 'to_status'])]
#[UseFactory(RequestStatusHistoryFactory::class)]
final class RequestStatusHistory extends Model
{
    /** @use HasFactory<RequestStatusHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return array<string, class-string<RequestStatus>> */
    protected function casts(): array
    {
        return [
            'from_status' => RequestStatus::class,
            'to_status' => RequestStatus::class,
        ];
    }
}
