<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class RequestTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    #[TestWith(['resident'])]
    #[TestWith(['manager'])]
    public function test_foreign_request_id_and_community_are_hidden(string $role): void
    {
        $member = CommunityMember::factory()->create(['role' => $role]);
        $foreign = ServiceRequest::factory()->create();
        $this->actingAs($member->user);

        $this->get(route('communities.requests.show', [
            'community' => $member->community->slug,
            'request' => $foreign->id,
            'community_id' => $foreign->community_id,
            'user_id' => $foreign->user_id,
            'role' => 'manager',
        ]))->assertNotFound();
        $this->get(route('communities.requests.show', [
            'community' => $foreign->community->slug,
            'request' => $foreign->id,
        ]))->assertNotFound();
        $this->post(route(
            'communities.requests.store',
            $foreign->community->slug,
        ), [
            'title' => 'Не горит свет',
            'description' => 'Подробное описание проблемы.',
        ])->assertNotFound();
        $this->assertDatabaseCount('service_requests', 1);
        $this->assertDatabaseCount('request_status_histories', 0);
    }

    #[TestWith(['0'])]
    #[TestWith(['999999999999999999999999999'])]
    #[TestWith(['not-a-number'])]
    public function test_invalid_or_missing_ids_are_not_found(string $id): void
    {
        $member = CommunityMember::factory()->resident()->create();

        $this->actingAs($member->user)->get(route('communities.requests.show', [
            'community' => $member->community->slug,
            'request' => $id,
        ]))->assertNotFound();
    }

    public function test_guest_is_redirected_on_every_request_route(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $slug = $serviceRequest->community->slug;

        $this->get(route('communities.requests.index', $slug))
            ->assertRedirectToRoute('login');
        $this->get(route('communities.requests.create', $slug))
            ->assertRedirectToRoute('login');
        $this->get(route('communities.requests.show', [
            'community' => $slug,
            'request' => $serviceRequest->id,
        ]))->assertRedirectToRoute('login');
        $this->post(route('communities.requests.store', $slug), [
            'title' => 'Не горит свет',
            'description' => 'Подробное описание проблемы.',
        ])->assertRedirectToRoute('login');
        $this->assertDatabaseCount('service_requests', 1);
        $this->assertDatabaseCount('request_status_histories', 0);
    }

    public function test_membership_deletion_revokes_all_access_on_the_next_request(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $slug = $serviceRequest->community->slug;
        $this->actingAs($serviceRequest->author)
            ->get(route('communities.requests.index', $slug))
            ->assertOk();
        $serviceRequest->community
            ->communityMembers()
            ->where('user_id', $serviceRequest->user_id)
            ->delete();

        $this->get(route('communities.requests.index', $slug))
            ->assertNotFound();
        $this->get(route('communities.requests.create', $slug))
            ->assertNotFound();
        $this->get(route('communities.requests.show', [
            'community' => $slug,
            'request' => $serviceRequest->id,
        ]))->assertNotFound();
        $this->post(route('communities.requests.store', $slug), [
            'title' => 'Не горит свет',
            'description' => 'Подробное описание проблемы.',
        ])->assertNotFound();
        $this->assertDatabaseCount('service_requests', 1);
    }

    public function test_roles_follow_each_community_and_updates_take_effect_on_next_request(): void
    {
        $resident = CommunityMember::factory()->resident()->create();
        $manager = CommunityMember::factory()->manager()->for($resident->user)->create();
        $own = ServiceRequest::factory()
            ->for($resident->community)
            ->for($resident->user, 'author')
            ->create();
        ServiceRequest::factory()->for($resident->community)->create();
        $managed = ServiceRequest::factory()->for($manager->community)->create();
        $this->actingAs($resident->user);

        $this->get(route(
            'communities.requests.index',
            $resident->community->slug,
        ))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('requests.total', 1)
                ->where('requests.data.0.id', $own->id),
            );
        $this->get(route(
            'communities.requests.index',
            $manager->community->slug,
        ))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('requests.total', 1)
                ->where('requests.data.0.id', $managed->id)
                ->where('createUrl', null),
            );
        $this->get(route('communities.requests.show', [
            'community' => $resident->community->slug,
            'request' => $managed->id,
        ]))->assertNotFound();
        $this->post(route(
            'communities.requests.store',
            $manager->community->slug,
        ), [
            'title' => 'Не горит свет',
            'description' => 'Подробное описание проблемы.',
            'role' => 'resident',
        ])->assertForbidden();
        $manager->update(['role' => CommunityRole::Resident]);
        $this->get(route(
            'communities.requests.index',
            $manager->community->slug,
        ))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('requests.total', 0));
        $this->get(route('communities.requests.show', [
            'community' => $manager->community->slug,
            'request' => $managed->id,
        ]))->assertForbidden();
    }
}
