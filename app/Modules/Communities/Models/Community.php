<?php

declare(strict_types=1);

namespace App\Modules\Communities\Models;

use App\Modules\Identity\Models\User;
use Database\Factories\CommunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description'])]
#[UseFactory(CommunityFactory::class)]
final class Community extends Model
{
    /** @use HasFactory<CommunityFactory> */
    use HasFactory;

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'community_members',
        )->withTimestamps();
    }

    /** @return HasMany<CommunityMember, $this> */
    public function communityMembers(): HasMany
    {
        return $this->hasMany(CommunityMember::class);
    }
}
