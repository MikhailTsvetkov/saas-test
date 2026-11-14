<?php

declare(strict_types=1);

namespace Tests\Feature\Inertia;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\CommunityMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class SharedPropsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_guest_receives_null_user_and_community(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('auth.user', null)
                ->where('community', null)
                ->where('urls.login', route('login'))
                ->where('urls.loginStore', route('login.store'))
                ->where('urls.logout', route('logout')));
    }

    public function test_only_public_user_fields_are_shared_even_when_memberships_are_loaded(): void
    {
        $membership = CommunityMember::factory()->manager()->create();
        $user = $membership->user->load('communityMembers.community', 'communities');

        $this->actingAs($user)->get(route('communities.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Communities/Index')
                ->where('auth.user', [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->where('community', null)
                ->has('communities', 1)
                ->missing('communityMembers'));
    }

    public function test_login_validation_errors_remain_available_to_inertia(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'))
            ->assertRedirectToRoute('login');

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('errors.email', 'Введите email.')
                ->where('errors.password', 'Введите пароль.')
                ->where('auth.user', null));
    }

    public function test_context_and_navigation_follow_each_community_and_clear_on_the_list(): void
    {
        $manager = CommunityMember::factory()->manager()->create();
        $resident = CommunityMember::factory()->resident()->for($manager->user)->create();

        $this->actingAs($manager->user)
            ->get(route(
                'communities.dashboard',
                $manager->community->slug,
            ))
            ->assertInertia(fn (Assert $page) => $page
                ->where('community.id', $manager->community_id)
                ->where('community.role', 'manager')
                ->where('urls.requests', route(
                    'communities.requests.index',
                    $manager->community->slug,
                ))
                ->where('urls.members', route(
                    'communities.members',
                    $manager->community->slug,
                )));

        $this->get(route(
            'communities.dashboard',
            $resident->community->slug,
        ))
            ->assertInertia(fn (Assert $page) => $page
                ->where('community.id', $resident->community_id)
                ->where('community.role', 'resident')
                ->where('urls.requests', route(
                    'communities.requests.index',
                    $resident->community->slug,
                ))
                ->where('urls.members', null));

        $this->get(route('communities.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('community', null)
                ->where('urls.dashboard', null)
                ->where('urls.requests', null)
                ->where('urls.members', null));
    }

    public function test_partial_reload_refreshes_identity_context_and_links_after_role_change(): void
    {
        $membership = CommunityMember::factory()->manager()->create();
        $url = route(
            'communities.dashboard',
            $membership->community->slug,
        );
        $response = $this->actingAs($membership->user)->get($url)->assertOk();
        $membership->update(['role' => CommunityRole::Resident]);

        $this->get($url, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $response->viewData('page')['version'],
            'X-Inertia-Partial-Component' => 'Communities/Dashboard',
            'X-Inertia-Partial-Data' => 'description',
        ])->assertOk()
            ->assertJsonPath('props.community.role', 'resident')
            ->assertJsonPath('props.community.id', $membership->community_id)
            ->assertJsonPath('props.auth.user.id', $membership->user_id)
            ->assertJsonPath('props.urls.members', null)
            ->assertJsonPath('props.urls.dashboard', $url)
            ->assertJsonPath('props.urls.requests', route(
                'communities.requests.index',
                $membership->community->slug,
            ))
            ->assertJsonMissingPath('props.appName');
    }

    public function test_inertia_partial_navigation_uses_the_requested_community_and_clears_it_on_index(): void
    {
        $manager = CommunityMember::factory()->manager()->create();
        $resident = CommunityMember::factory()->resident()->for($manager->user)->create();
        $response = $this->actingAs($manager->user)
            ->get(route(
                'communities.dashboard',
                $manager->community->slug,
            ))->assertOk();
        $headers = [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $response->viewData('page')['version'],
            'X-Inertia-Partial-Component' => 'Communities/Dashboard',
            'X-Inertia-Partial-Data' => 'description',
        ];

        $this->get(route(
            'communities.dashboard',
            $resident->community->slug,
        ), $headers)
            ->assertOk()
            ->assertJsonPath('props.community.id', $resident->community_id)
            ->assertJsonPath('props.community.role', 'resident')
            ->assertJsonPath('props.urls.members', null)
            ->assertJsonPath('props.urls.requests', route(
                'communities.requests.index',
                $resident->community->slug,
            ))
            ->assertJsonPath('props.urls.dashboard', route(
                'communities.dashboard',
                $resident->community->slug,
            ));

        $this->get(route('communities.index'), $headers)
            ->assertOk()
            ->assertJsonPath('props.community', null)
            ->assertJsonPath('props.urls.dashboard', null)
            ->assertJsonPath('props.urls.requests', null)
            ->assertJsonPath('props.urls.members', null)
            ->assertJsonCount(2, 'props.communities');
    }

    public function test_partial_request_navigation_updates_creation_link_and_filters_for_the_new_community(): void
    {
        $resident = CommunityMember::factory()->resident()->create();
        $manager = CommunityMember::factory()->manager()->for($resident->user)->create();
        $response = $this->actingAs($resident->user)
            ->get(route(
                'communities.requests.index',
                $resident->community->slug,
            ))->assertOk();

        $this->get(route('communities.requests.index', [
            'community' => $manager->community->slug,
            'status' => 'resolved',
        ]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $response->viewData('page')['version'],
            'X-Inertia-Partial-Component' => 'Requests/Index',
            'X-Inertia-Partial-Data' => 'requests',
        ])->assertOk()
            ->assertJsonPath('props.createUrl', null)
            ->assertJsonPath('props.filters.status', 'resolved')
            ->assertJsonPath('props.community.id', $manager->community_id)
            ->assertJsonPath('props.urls.requests', route(
                'communities.requests.index',
                $manager->community->slug,
            ));
    }
}
