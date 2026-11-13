<?php

declare(strict_types=1);

namespace Tests\Feature\Communities;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Communities\Models\Community;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class CommunityPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['resident', false])]
    #[TestWith(['manager', true])]
    public function test_members_can_view_the_community_but_only_managers_can_view_members(
        string $role,
        bool $canViewMembers,
    ): void {
        $membership = CommunityMember::factory()->{$role}()->create();
        $gate = Gate::forUser($membership->user);

        $this->assertTrue($gate->allows('view', $membership->community));
        $this->assertSame(
            $canViewMembers,
            $gate->allows('viewMembers', $membership->community),
        );
    }

    #[TestWith(['view'])]
    #[TestWith(['viewMembers'])]
    public function test_manager_of_another_community_receives_a_not_found_denial(
        string $ability,
    ): void {
        $membership = CommunityMember::factory()->manager()->create();
        $otherCommunity = Community::factory()->create();

        $response = Gate::forUser($membership->user)->inspect($ability, $otherCommunity);

        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
    }

    #[TestWith(['view'])]
    #[TestWith(['viewMembers'])]
    public function test_user_without_any_membership_receives_a_not_found_denial(
        string $ability,
    ): void {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $response = Gate::forUser($user)->inspect($ability, $community);

        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
    }

    public function test_permissions_use_the_requested_user_and_community_instead_of_current_context(): void
    {
        $manager = CommunityMember::factory()->manager()->create();
        $resident = CommunityMember::factory()->for($manager->user)->resident()->create();
        $outsider = User::factory()->create();
        $this->app->make(CurrentCommunity::class)
            ->set($manager->community, $manager);

        $this->assertFalse(Gate::forUser($manager->user)
            ->allows('viewMembers', $resident->community));
        $this->assertFalse(Gate::forUser($outsider)
            ->allows('viewMembers', $manager->community));
        $this->assertTrue(Gate::forUser($manager->user)
            ->allows('viewMembers', $manager->community));
    }

    #[TestWith(['view'])]
    #[TestWith(['viewMembers'])]
    public function test_deleted_membership_is_not_authorized_from_loaded_relationships(
        string $ability,
    ): void {
        $membership = CommunityMember::factory()->manager()->create();
        $user = $membership->user->load('communityMembers');
        $community = $membership->community->load('communityMembers');
        $gate = Gate::forUser($user);
        $this->assertTrue($gate->allows($ability, $community));

        $membership->delete();

        $this->assertSame(404, $gate->inspect($ability, $community)->status());
    }
}
