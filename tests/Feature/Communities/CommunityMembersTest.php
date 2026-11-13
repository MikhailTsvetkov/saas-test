<?php

declare(strict_types=1);

namespace Tests\Feature\Communities;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\CommunityMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CommunityMembersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_manager_sees_only_current_community_members_with_public_fields(): void
    {
        $manager = CommunityMember::factory()->manager()->create();
        $resident = CommunityMember::factory()->resident()->for($manager->community)->create();
        $other = CommunityMember::factory()->manager()->create();
        CommunityMember::factory()->resident()->for($manager->user)->for($other->community)->create();

        $this->actingAs($manager->user)->get(route('communities.members', [
            'community' => $manager->community->slug,
            'community_id' => $other->community_id,
            'user_id' => $other->user_id,
            'role' => 'resident',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Communities/Members')
            ->where('community.id', $manager->community_id)
            ->where('members', [
                [
                    'id' => $manager->user_id,
                    'name' => $manager->user->name,
                    'email' => $manager->user->email,
                    'role' => 'manager',
                ],
                [
                    'id' => $resident->user_id,
                    'name' => $resident->user->name,
                    'email' => $resident->user->email,
                    'role' => 'resident',
                ],
            ]));
    }

    public function test_manager_in_another_community_cannot_open_members_as_a_resident(): void
    {
        $manager = CommunityMember::factory()->manager()->create();
        $resident = CommunityMember::factory()->resident()->for($manager->user)->create();

        $this->actingAs($manager->user)->get(route('communities.members', [
            'community' => $resident->community->slug,
            'role' => 'manager',
            'community_id' => $manager->community_id,
        ]))->assertForbidden();
    }

    public function test_outsider_and_unknown_community_return_404(): void
    {
        $manager = CommunityMember::factory()->manager()->create();
        $other = CommunityMember::factory()->create();

        $this->actingAs($manager->user)
            ->get(route(
                'communities.members',
                $other->community->slug,
            ))->assertNotFound();
        $this->get(route(
            'communities.members',
            'unknown-community',
        ))->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route(
            'communities.members',
            'unknown-community',
        ))->assertRedirectToRoute('login');
    }

    public function test_demoted_manager_loses_member_list_access_immediately(): void
    {
        $membership = CommunityMember::factory()->manager()->create();
        $url = route(
            'communities.members',
            $membership->community->slug,
        );
        $this->actingAs($membership->user)->get($url)->assertOk();

        $membership->update(['role' => CommunityRole::Resident]);

        $this->get($url)->assertForbidden();
    }
}
