<?php

declare(strict_types=1);

namespace Tests\Feature\Communities;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\Community;
use App\Modules\Communities\Models\CommunityMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use LogicException;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

final class ResolveCommunityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $respond = static function (
            Community $community,
            CurrentCommunity $context,
        ): JsonResponse {
            return response()->json([
                'bound_community_id' => $community->id,
                'community_id' => $context->community()->id,
                'membership_id' => $context->membership()->id,
                'user_id' => $context->membership()->user_id,
                'role' => $context->role()->value,
            ]);
        };

        Route::middleware(['web', 'auth', 'community'])->group(function () use (
            $respond,
        ): void {
            Route::get('/_test/communities/{community:slug}', $respond)
                ->middleware('can:view,community');
            Route::get('/_test/communities/{community:slug}/members', $respond)
                ->middleware('can:viewMembers,community');
            Route::get('/_test/communities/{community:slug}/failure', static function (
                Community $community,
                CurrentCommunity $context,
            ): never {
                $context->community();

                throw new RuntimeException('Failed after resolving community.');
            });
            Route::get('/_test/missing-binding', static fn (): JsonResponse => response()->json());
        });

        Route::middleware(['web', 'community'])
            ->get('/_test/unguarded/{community:slug}', $respond);
        Route::middleware('web')
            ->get('/_test/no-community', static fn (
                CurrentCommunity $context,
            ): JsonResponse => response()->json([
                'has_community' => $context->isSet(),
            ]));
    }

    #[TestWith(['existing'])]
    #[TestWith(['unknown'])]
    public function test_guest_is_redirected_before_community_binding(string $slug): void
    {
        Community::factory()->create(['slug' => 'existing']);

        $this->get('/_test/communities/'.$slug)->assertRedirectToRoute('login');

        $this->assertFalse($this->app->make(CurrentCommunity::class)->isSet());
    }

    public function test_middleware_itself_requires_authentication(): void
    {
        $community = Community::factory()->create();

        $this->get('/_test/unguarded/'.$community->slug)
            ->assertRedirectToRoute('login');
    }

    public function test_member_receives_the_bound_community_and_their_own_membership(): void
    {
        $membership = CommunityMember::factory()->resident()->create();

        $this->actingAs($membership->user)
            ->get('/_test/communities/'.$membership->community->slug)
            ->assertOk()
            ->assertExactJson([
                'bound_community_id' => $membership->community_id,
                'community_id' => $membership->community_id,
                'membership_id' => $membership->id,
                'user_id' => $membership->user_id,
                'role' => 'resident',
            ]);

        $this->assertFalse($this->app->make(CurrentCommunity::class)->isSet());
    }

    #[TestWith(['resident'])]
    #[TestWith(['manager'])]
    public function test_member_of_another_community_receives_404(string $role): void
    {
        $membership = CommunityMember::factory()->{$role}()->create();
        $otherCommunity = Community::factory()->create();

        $this->actingAs($membership->user)
            ->get('/_test/communities/'.$otherCommunity->slug)
            ->assertNotFound();

        $this->assertFalse($this->app->make(CurrentCommunity::class)->isSet());
    }

    public function test_unknown_slug_returns_404_for_authenticated_users(): void
    {
        $membership = CommunityMember::factory()->create();

        $this->actingAs($membership->user)
            ->get('/_test/communities/unknown')
            ->assertNotFound();
    }

    public function test_resident_receives_403_for_members_and_context_is_cleared(): void
    {
        $membership = CommunityMember::factory()->resident()->create();

        $this->actingAs($membership->user)
            ->get('/_test/communities/' . $membership->community->slug . '/members')
            ->assertForbidden();

        $this->assertFalse($this->app->make(CurrentCommunity::class)->isSet());
    }

    public function test_manager_can_pass_members_authorization(): void
    {
        $membership = CommunityMember::factory()->manager()->create();

        $this->actingAs($membership->user)
            ->get('/_test/communities/' . $membership->community->slug . '/members')
            ->assertOk()
            ->assertJsonPath('role', 'manager');
    }

    public function test_client_parameters_cannot_replace_the_bound_community_user_or_role(): void
    {
        $membership = CommunityMember::factory()->resident()->create();
        $otherMembership = CommunityMember::factory()->manager()->create();
        $query = http_build_query([
            'community_id' => $otherMembership->community_id,
            'user_id' => $otherMembership->user_id,
            'role' => 'manager',
        ]);

        $this->actingAs($membership->user)
            ->get('/_test/communities/'.$membership->community->slug . '?' . $query)
            ->assertOk()
            ->assertJsonPath('community_id', $membership->community_id)
            ->assertJsonPath('user_id', $membership->user_id)
            ->assertJsonPath('role', 'resident');
        $this->get('/_test/communities/'.$membership->community->slug . '/members?' . $query)
            ->assertForbidden();
    }

    public function test_switching_communities_and_leaving_tenant_routes_does_not_keep_previous_context(): void
    {
        $resident = CommunityMember::factory()->resident()->create();
        $manager = CommunityMember::factory()->for($resident->user)->manager()->create();

        $this->actingAs($resident->user)
            ->get('/_test/communities/' . $resident->community->slug)
            ->assertOk()
            ->assertJsonPath('role', 'resident');
        $this->get('/_test/communities/'.$manager->community->slug)
            ->assertOk()
            ->assertJsonPath('community_id', $manager->community_id)
            ->assertJsonPath('role', 'manager');
        $this->get('/_test/no-community')
            ->assertOk()
            ->assertExactJson(['has_community' => false]);
    }

    public function test_deleted_membership_revokes_access_on_the_next_request(): void
    {
        $membership = CommunityMember::factory()->manager()->create();
        $user = $membership->user->load('communityMembers');
        $path = '/_test/communities/' . $membership->community->slug;
        $this->actingAs($user)->get($path)->assertOk();

        $membership->delete();

        $this->get($path)->assertNotFound();
        $this->assertFalse($this->app->make(CurrentCommunity::class)->isSet());
    }

    public function test_role_changes_take_effect_on_the_next_request(): void
    {
        $membership = CommunityMember::factory()->manager()->create();
        $path = '/_test/communities/' . $membership->community->slug;
        $this->actingAs($membership->user)->get($path . '/members')->assertOk();

        $membership->update(['role' => CommunityRole::Resident]);

        $this->get($path)->assertOk()->assertJsonPath('role', 'resident');
        $this->get($path.'/members')->assertForbidden();
    }

    public function test_switching_users_does_not_reuse_the_previous_users_role(): void
    {
        $manager = CommunityMember::factory()->manager()->create();
        $resident = CommunityMember::factory()->for($manager->community)->resident()->create();
        $path = '/_test/communities/'.$manager->community->slug;
        $this->actingAs($manager->user)->get($path . '/members')->assertOk();

        $this->actingAs($resident->user)->get($path)->assertOk()
            ->assertJsonPath('membership_id', $resident->id)
            ->assertJsonPath('role', 'resident');
        $this->get($path.'/members')->assertForbidden();
    }

    public function test_context_is_cleared_when_downstream_code_throws(): void
    {
        $membership = CommunityMember::factory()->create();
        Exceptions::fake();

        $this->actingAs($membership->user)
            ->get('/_test/communities/' . $membership->community->slug . '/failure')
            ->assertServerError();

        Exceptions::assertReported(RuntimeException::class);
        $this->get('/_test/no-community')
            ->assertOk()
            ->assertExactJson(['has_community' => false]);
    }

    public function test_missing_route_binding_reports_a_configuration_error(): void
    {
        $membership = CommunityMember::factory()->create();
        Exceptions::fake();

        $this->actingAs($membership->user)
            ->get('/_test/missing-binding')
            ->assertServerError();

        Exceptions::assertReported(fn (
            LogicException $exception,
        ): bool => $exception->getMessage() === 'ResolveCommunity requires a route-bound Community.');

        $this->assertFalse($this->app->make(CurrentCommunity::class)->isSet());
    }
}
