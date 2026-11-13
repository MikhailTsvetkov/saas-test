<?php

declare(strict_types=1);

namespace App\Modules\Communities\Models;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Identity\Models\User;
use Database\Factories\CommunityMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['community_id', 'user_id', 'role'])]
#[UseFactory(CommunityMemberFactory::class)]
final class CommunityMember extends Model
{
    /** @use HasFactory<CommunityMemberFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Community, $this> */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /** @return array<string, class-string<CommunityRole>> */
    protected function casts(): array
    {
        return [
            'role' => CommunityRole::class,
        ];
    }
}
