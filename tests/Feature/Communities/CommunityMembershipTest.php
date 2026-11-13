<?php

declare(strict_types=1);

namespace Tests\Feature\Communities;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\Community;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Identity\Models\User;
use Database\Factories\CommunityFactory;
use Database\Factories\CommunityMemberFactory;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class CommunityMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_independent_memberships_and_roles_in_each_community(): void
    {
        $user = User::factory()->create();
        $residentMembership = CommunityMember::factory()->for($user)->resident()->create();
        $managerMembership = CommunityMember::factory()->for($user)->manager()->create();
        CommunityMember::factory()->create();

        $memberships = $user->communityMembers()->orderBy('id')->get();

        $this->assertSame(
            [$residentMembership->id, $managerMembership->id],
            $memberships->modelKeys(),
        );
        $this->assertSame(
            [CommunityRole::Resident, CommunityRole::Manager],
            $memberships->pluck('role')->all(),
        );
        $this->assertSame(
            [$residentMembership->community_id, $managerMembership->community_id],
            $user->communities()->orderBy('communities.id')->get()->modelKeys(),
        );

        $community = $residentMembership->community;
        $this->assertSame([$user->id], $community->users->modelKeys());
        $this->assertSame(
            [$residentMembership->id],
            $community->communityMembers->modelKeys(),
        );
        $this->assertTrue($residentMembership->user->is($user));
    }

    public function test_factories_create_modular_models_without_automatic_namespace_discovery(): void
    {
        $community = CommunityFactory::new()->create(['description' => null]);

        $membership = CommunityMemberFactory::new()->for($community)->create();

        $this->assertModelExists($community);
        $this->assertNull($community->fresh()->description);
        $this->assertModelExists($membership);
        $this->assertTrue($membership->community->is($community));
        $this->assertModelExists($membership->user);
        $this->assertSame(CommunityRole::Resident, $membership->fresh()->role);
    }

    public function test_role_changes_are_persisted_only_for_the_selected_membership(): void
    {
        $membership = CommunityMember::factory()->resident()->create();
        $otherMembership = CommunityMember::factory()->for($membership->user)->resident()->create();

        $membership->update(['role' => CommunityRole::Manager]);

        $this->assertSame(CommunityRole::Manager, $membership->fresh()->role);
        $this->assertSame(CommunityRole::Resident, $otherMembership->fresh()->role);
        $this->assertDatabaseHas(
            'community_members',
            ['id' => $membership->id, 'role' => 'manager'],
        );
    }

    public function test_rejects_duplicate_community_slugs(): void
    {
        Community::factory()->create(['slug' => 'sosnovy']);

        $this->expectException(UniqueConstraintViolationException::class);

        Community::factory()->create(['slug' => 'sosnovy']);
    }

    public function test_rejects_duplicate_memberships_even_with_a_different_role(): void
    {
        $membership = CommunityMember::factory()->resident()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        CommunityMember::factory()
            ->for($membership->community)
            ->for($membership->user)
            ->manager()
            ->create();
    }

    #[TestWith(['community_id'])]
    #[TestWith(['user_id'])]
    public function test_rejects_memberships_with_a_missing_parent(string $foreignKey): void
    {
        $membership = CommunityMember::factory()
            ->for(Community::factory()->create())
            ->for(User::factory()->create())
            ->make();
        $membership->{$foreignKey} = 0;

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23503');

        $membership->save();
    }

    public function test_deleting_a_community_removes_only_its_memberships_and_preserves_users(): void
    {
        $membership = CommunityMember::factory()->create();
        $user = $membership->user;
        $otherMembership = CommunityMember::factory()->for($user)->create();

        $membership->community->delete();

        $this->assertModelMissing($membership);
        $this->assertModelExists($user);
        $this->assertModelExists($otherMembership);
        $this->assertSame(
            [$otherMembership->community_id],
            $user->communities->modelKeys(),
        );
    }

    public function test_deleting_a_user_removes_only_their_memberships_and_preserves_communities(): void
    {
        $membership = CommunityMember::factory()->create();
        $community = $membership->community;
        $otherMembership = CommunityMember::factory()->for($community)->create();

        $membership->user->delete();

        $this->assertModelMissing($membership);
        $this->assertModelExists($community);
        $this->assertModelExists($otherMembership);
        $this->assertSame([$otherMembership->user_id], $community->users->modelKeys());
    }
}
