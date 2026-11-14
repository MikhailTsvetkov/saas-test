<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\Modules\Communities\Models\Community;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RequestSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_mutations_require_valid_csrf_tokens(): void
    {
        $community = Community::factory()->create();
        $resident = CommunityMember::factory()->for($community)->resident()->create();
        $manager = CommunityMember::factory()->for($community)->manager()->create();
        $serviceRequest = ServiceRequest::factory()->for($community)->for($resident->user, 'author')->create();

        $this->enableCsrfProtection();

        $this->actingAs($resident->user)
            ->withSession(['_token' => 'resident-token'])
            ->post(route('communities.requests.store', $community->slug), [
                'title' => 'Не горит свет',
                'description' => 'В подъезде не горит свет на первом этаже.',
                '_token' => 'wrong-token',
            ])
            ->assertStatus(419);

        $this->actingAs($manager->user)
            ->withSession(['_token' => 'manager-token'])
            ->post(route('communities.requests.messages.store', [
                'community' => $community->slug,
                'request' => $serviceRequest->id,
            ]), ['body' => 'Тестовый ответ', '_token' => 'wrong-token'])
            ->assertStatus(419);

        $this->actingAs($manager->user)
            ->withSession(['_token' => 'status-token'])
            ->patch(route('communities.requests.status.update', [
                'community' => $community->slug,
                'request' => $serviceRequest->id,
            ]), ['status' => 'in_progress', '_token' => 'wrong-token'])
            ->assertStatus(419);

        $this->assertDatabaseCount('service_requests', 1);
        $this->assertDatabaseCount('request_messages', 0);
        $this->assertDatabaseCount('request_status_histories', 0);
    }

    public function test_edit_and_delete_hooks_are_not_exposed_for_requests(): void
    {
        $community = Community::factory()->create();
        $member = CommunityMember::factory()->for($community)->manager()->create();
        $serviceRequest = ServiceRequest::factory()->for($community)->for($member->user, 'author')->create();

        $this->actingAs($member->user)
            ->delete(route('communities.requests.show', [
                'community' => $community->slug,
                'request' => $serviceRequest->id,
            ]))
            ->assertStatus(405);

        $this->actingAs($member->user)
            ->put(route('communities.requests.status.update', [
                'community' => $community->slug,
                'request' => $serviceRequest->id,
            ]), ['status' => 'in_progress'])
            ->assertStatus(405);

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'status' => 'new',
        ]);
    }

    private function enableCsrfProtection(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn () => new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }
}
