<?php

declare(strict_types=1);

namespace App\Modules\Requests\Models;

use App\Modules\Identity\Models\User;
use Database\Factories\RequestMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_request_id', 'user_id', 'body'])]
#[UseFactory(RequestMessageFactory::class)]
final class RequestMessage extends Model
{
    /** @use HasFactory<RequestMessageFactory> */
    use HasFactory;

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
}
