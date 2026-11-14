<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ChangeRequestStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_change_status_in_the_current_community(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $manager = CommunityMember::factory()
            ->for($serviceRequest->community)
            ->manager()
            ->create();

        $this->actingAs($manager->user)
            ->patch(route('communities.requests.status.update', [
                'community' => $serviceRequest->community->slug,
                'request' => $serviceRequest->id,
            ]), ['status' => 'in_progress'])
            ->assertRedirect(route('communities.requests.show', [
                'community' => $serviceRequest->community->slug,
                'request' => $serviceRequest->id,
            ]));

        $this->assertSame(
            RequestStatus::InProgress,
            $serviceRequest->fresh()->status,
        );
        $this->assertNull($serviceRequest->fresh()->resolved_at);
        $this->assertDatabaseHas('request_status_histories', [
            'service_request_id' => $serviceRequest->id,
            'user_id' => $manager->user_id,
            'from_status' => RequestStatus::New->value,
            'to_status' => RequestStatus::InProgress->value,
        ]);
    }

    public function test_resolved_request_can_be_reopened_and_resolution_time_is_cleared(): void
    {
        $serviceRequest = ServiceRequest::factory()->resolved()->create();
        $manager = CommunityMember::factory()->for($serviceRequest->community)->manager()->create();

        $this->actingAs($manager->user)
            ->patch(route('communities.requests.status.update', [
                'community' => $serviceRequest->community->slug,
                'request' => $serviceRequest->id,
            ]), ['status' => 'in_progress'])
            ->assertRedirect();

        $this->assertSame(RequestStatus::InProgress, $serviceRequest->fresh()->status);
        $this->assertNull($serviceRequest->fresh()->resolved_at);
        $this->assertDatabaseHas('request_status_histories', [
            'service_request_id' => $serviceRequest->id,
            'user_id' => $manager->user_id,
            'from_status' => RequestStatus::Resolved->value,
            'to_status' => RequestStatus::InProgress->value,
        ]);
    }

    public function test_forbidden_transition_is_rejected_without_saving_history_or_status(): void
    {
        $serviceRequest = ServiceRequest::factory()->inProgress()->create();
        $manager = CommunityMember::factory()
            ->for($serviceRequest->community)
            ->manager()
            ->create();

        $this->actingAs($manager->user)
            ->from(route('communities.requests.show', [
                'community' => $serviceRequest->community->slug,
                'request' => $serviceRequest->id,
            ]))
            ->patch(route('communities.requests.status.update', [
                'community' => $serviceRequest->community->slug,
                'request' => $serviceRequest->id,
            ]), ['status' => 'new'])
            ->assertSessionHasErrors('status');

        $this->assertSame(RequestStatus::InProgress, $serviceRequest->fresh()->status);
        $this->assertDatabaseCount('request_status_histories', 0);
    }

    public function test_resident_cannot_change_status(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $resident = CommunityMember::factory()
            ->for($serviceRequest->community)
            ->resident()
            ->create();

        $this->actingAs($resident->user)
            ->patch(route('communities.requests.status.update', [
                'community' => $serviceRequest->community->slug,
                'request' => $serviceRequest->id,
            ]), ['status' => 'in_progress'])
            ->assertForbidden();

        $this->assertSame(RequestStatus::New, $serviceRequest->fresh()->status);
        $this->assertDatabaseCount('request_status_histories', 0);
    }
}
