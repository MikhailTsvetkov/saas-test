<?php

declare(strict_types=1);

namespace Tests\Feature\Communities;

use App\Modules\Communities\Models\Community;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CommunityIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('communities.index'))
            ->assertRedirectToRoute('login');
    }

    public function test_list_contains_only_own_communities_and_roles_in_stable_order(): void
    {
        $user = User::factory()->create();
        $first = Community::factory()->create(['description' => null]);
        $second = Community::factory()->create();
        CommunityMember::factory()->manager()->for($user)->for($second)->create();
        CommunityMember::factory()->resident()->for($user)->for($first)->create();
        $outsider = CommunityMember::factory()->create();

        $this->actingAs($user)->get(route('communities.index', [
            'user_id' => $outsider->user_id,
            'community_id' => $outsider->community_id,
            'role' => 'manager',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Communities/Index')
            ->where('community', null)
            ->where('communities', [
                [
                    'id' => $first->id,
                    'name' => $first->name,
                    'slug' => $first->slug,
                    'description' => null,
                    'role' => 'resident',
                    'dashboardUrl' => route(
                        'communities.dashboard',
                        $first->slug,
                    ),
                ],
                [
                    'id' => $second->id,
                    'name' => $second->name,
                    'slug' => $second->slug,
                    'description' => $second->description,
                    'role' => 'manager',
                    'dashboardUrl' => route(
                        'communities.dashboard',
                        $second->slug,
                    ),
                ],
            ]));
    }

    public function test_user_without_memberships_receives_an_empty_list(): void
    {
        CommunityMember::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('communities.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Communities/Index')
                ->where('communities', [])
                ->where('community', null)
                ->where('urls.dashboard', null)
                ->where('urls.members', null));
    }

    public function test_deleted_membership_disappears_from_the_next_list(): void
    {
        $membership = CommunityMember::factory()->create();
        $this->actingAs($membership->user)
            ->get(route('communities.index'))
            ->assertInertia(fn (Assert $page) => $page->has('communities', 1));

        $membership->delete();

        $this->get(route('communities.index'))
            ->assertInertia(fn (Assert $page) => $page->where('communities', []));
    }
}
