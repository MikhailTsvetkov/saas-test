<?php

declare(strict_types=1);

namespace Tests\Feature\Communities;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\CommunityMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class CommunityDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    #[TestWith([CommunityRole::Resident])]
    #[TestWith([CommunityRole::Manager])]
    public function test_member_can_view_own_dashboard_with_safe_current_community_props(
        CommunityRole $role,
    ): void {
        $membership = CommunityMember::factory()->create(['role' => $role]);
        $community = $membership->community;

        $this->actingAs($membership->user)
            ->get(route(
                'communities.dashboard',
                $community->slug,
            ))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Communities/Dashboard')
                ->where('description', $community->description)
                ->where('community', [
                    'id' => $community->id,
                    'name' => $community->name,
                    'slug' => $community->slug,
                    'role' => $role->value,
                ])
                ->where('urls.dashboard', route(
                    'communities.dashboard',
                    $community->slug,
                ))
                ->where('urls.members', $role === CommunityRole::Manager
                    ? route('communities.members', $community->slug)
                    : null
                )
                ->missing('members'));
    }

    public function test_guest_is_redirected_before_an_unknown_slug_is_resolved(): void
    {
        $this->get(route(
            'communities.dashboard',
            'unknown-community',
        ))->assertRedirectToRoute('login');
    }

    #[TestWith([CommunityRole::Resident])]
    #[TestWith([CommunityRole::Manager])]
    public function test_foreign_and_unknown_communities_return_404(CommunityRole $role): void
    {
        $member = CommunityMember::factory()->create(['role' => $role]);
        $other = CommunityMember::factory()->create();

        $this->actingAs($member->user)
            ->get(route(
                'communities.dashboard',
                $other->community->slug,
            ))->assertNotFound();
        $this->get(route(
            'communities.dashboard',
            'unknown-community',
        ))->assertNotFound();
    }

    public function test_deleted_membership_revokes_dashboard_access_on_the_next_request(): void
    {
        $membership = CommunityMember::factory()->create();
        $url = route(
            'communities.dashboard',
            $membership->community->slug,
        );
        $this->actingAs($membership->user)->get($url)->assertOk();

        $membership->delete();

        $this->get($url)->assertNotFound();
    }
}
