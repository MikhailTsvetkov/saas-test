<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Communities\Models\Community;
use App\Modules\Communities\Models\CommunityMember;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @return BelongsToMany<Community, $this> */
    public function communities(): BelongsToMany
    {
        return $this->belongsToMany(
            Community::class,
            'community_members',
        )->withTimestamps();
    }

    /** @return HasMany<CommunityMember, $this> */
    public function communityMembers(): HasMany
    {
        return $this->hasMany(CommunityMember::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
