<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Requests\Actions\ShowRequest;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Models\RequestStatusHistory;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class RequestShowTest extends TestCase
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
    public function test_show_serializes_only_public_details_and_ordered_history(string $role): void
    {
        $this->freezeSecond();
        $serviceRequest = ServiceRequest::factory()->resolved()->create();
        $member = CommunityMember::factory()
            ->for($serviceRequest->community)
            ->create(['role' => $role]);
        $viewer = $role === 'resident' ? $serviceRequest->author : $member->user;
        $resolution = RequestStatusHistory::factory()->for($serviceRequest)->create([
            'user_id' => null,
            'from_status' => RequestStatus::InProgress,
            'to_status' => RequestStatus::Resolved,
            'created_at' => now()->addMinute(),
        ]);
        $creation = RequestStatusHistory::factory()->for($serviceRequest)->create();
        $author = [
            'id' => $serviceRequest->author->id,
            'name' => $serviceRequest->author->name,
        ];

        $this->actingAs($viewer)->get(route('communities.requests.show', [
            'community' => $serviceRequest->community->slug,
            'request' => $serviceRequest->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Requests/Show')->where('serviceRequest', [
                'id' => $serviceRequest->id,
                'title' => $serviceRequest->title,
                'description' => $serviceRequest->description,
                'status' => 'resolved',
                'author' => $author,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
                'resolved_at' => now()->toISOString(),
                'statusHistories' => [
                    [
                        'id' => $creation->id,
                        'from_status' => null,
                        'to_status' => 'new',
                        'author' => $author,
                        'created_at' => now()->toISOString(),
                    ],
                    [
                        'id' => $resolution->id,
                        'from_status' => 'in_progress',
                        'to_status' => 'resolved',
                        'author' => null,
                        'created_at' => now()->addMinute()->toISOString(),
                    ],
                ],
            ]));
    }

    public function test_new_request_has_no_resolution_date(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();

        $this->actingAs($serviceRequest->author)->get(route('communities.requests.show', [
            'community' => $serviceRequest->community->slug,
            'request' => $serviceRequest->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('serviceRequest.resolved_at', null)
            ->where('serviceRequest.status', 'new')
            ->has('serviceRequest.statusHistories', 0));
    }

    public function test_resident_is_forbidden_from_viewing_another_residents_request(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $member = CommunityMember::factory()
            ->for($serviceRequest->community)
            ->resident()
            ->create();

        $this->actingAs($member->user)->get(route('communities.requests.show', [
            'community' => $serviceRequest->community->slug,
            'request' => $serviceRequest->id,
        ]))->assertForbidden();
    }

    public function test_direct_show_action_still_checks_authorization(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $member = CommunityMember::factory()
            ->for($serviceRequest->community)
            ->resident()
            ->create();
        $this->app->make(CurrentCommunity::class)
            ->set($member->community, $member);

        $this->expectException(AuthorizationException::class);

        $this->app->make(ShowRequest::class)
            ->handle($member->user, (string) $serviceRequest->id);
    }
}
