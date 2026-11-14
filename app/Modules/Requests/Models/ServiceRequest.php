<?php

declare(strict_types=1);

namespace App\Modules\Requests\Models;

use App\Modules\Communities\Models\Community;
use App\Modules\Identity\Models\User;
use App\Modules\Requests\Enums\RequestStatus;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['community_id', 'user_id', 'title', 'description', 'status', 'resolved_at'])]
#[UseFactory(ServiceRequestFactory::class)]
final class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory;

    /** @return BelongsTo<Community, $this> */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<RequestMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(RequestMessage::class);
    }

    /** @return HasMany<RequestStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'resolved_at' => 'datetime',
        ];
    }
}
