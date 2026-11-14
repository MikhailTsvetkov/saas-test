<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\Modules\Communities\Models\Community;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_resident_and_manager_complete_the_request_lifecycle(): void
    {
        $community = Community::factory()->create();
        $resident = CommunityMember::factory()->for($community)->resident()->create();
        $manager = CommunityMember::factory()->for($community)->manager()->create();

        $this->actingAs($resident->user)
            ->post(route('communities.requests.store', $community->slug), [
                'title' => 'Не горит свет',
                'description' => 'В подъезде не горит свет на первом этаже.',
            ])
            ->assertRedirect();

        $serviceRequest = ServiceRequest::query()->sole();

        $this->actingAs($manager->user)
            ->post(route('communities.requests.messages.store', [
                'community' => $community->slug,
                'request' => $serviceRequest->id,
            ]), ['body' => 'Проверим и устраним проблему сегодня.'])
            ->assertRedirectToRoute('communities.requests.show', [
                'community' => $community->slug,
                'request' => $serviceRequest->id,
            ]);

        $this->actingAs($manager->user)
            ->patch(route('communities.requests.status.update', [
                'community' => $community->slug,
                'request' => $serviceRequest->id,
            ]), ['status' => 'resolved'])
            ->assertRedirectToRoute('communities.requests.show', [
                'community' => $community->slug,
                'request' => $serviceRequest->id,
            ]);

        $this->actingAs($resident->user)
            ->get(route('communities.requests.show', [
                'community' => $community->slug,
                'request' => $serviceRequest->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('serviceRequest.status', 'resolved')
                ->where('serviceRequest.author.id', $resident->user_id)
                ->where('serviceRequest.statusHistories.1.to_status', 'resolved'));

        $this->actingAs($manager->user)
            ->patch(route('communities.requests.status.update', [
                'community' => $community->slug,
                'request' => $serviceRequest->id,
            ]), ['status' => 'in_progress'])
            ->assertRedirectToRoute('communities.requests.show', [
                'community' => $community->slug,
                'request' => $serviceRequest->id,
            ]);

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'status' => 'in_progress',
            'resolved_at' => null,
        ]);
    }
}
