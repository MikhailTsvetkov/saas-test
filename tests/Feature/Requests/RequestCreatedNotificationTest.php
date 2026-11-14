<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Requests\Events\RequestCreated;
use App\Modules\Requests\Listeners\SendRequestCreatedNotification;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class RequestCreatedNotificationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_creating_a_request_queues_the_notification_listener_after_commit(): void
    {
        $resident = CommunityMember::factory()->resident()->create();
        Queue::fake();

        $this->actingAs($resident->user)
            ->post(route(
                'communities.requests.store',
                $resident->community->slug,
            ), [
                'title' => 'Не горит свет',
                'description' => 'В подъезде не горит свет на первом этаже.',
            ])
            ->assertRedirect();

        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === SendRequestCreatedNotification::class
                && $job->data[0] instanceof RequestCreated
                && $job->data[0]->serviceRequest->community_id === $resident->community_id,
        );
    }

    public function test_local_notification_is_logged_only_for_managers_of_the_request_community(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $serviceRequest = ServiceRequest::factory()->create();
        $manager = CommunityMember::factory()
            ->for($serviceRequest->community)
            ->manager()
            ->create();
        CommunityMember::factory()->manager()->create();
        $logger = Log::spy();

        (new SendRequestCreatedNotification)->handle(new RequestCreated($serviceRequest));

        $logger->shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message
                    === 'Уведомление управляющему о новом обращении.'
                && $context['manager_id'] === $manager->user_id
                && $context['manager_email'] === $manager->user->email
                && $context['request_id'] === $serviceRequest->id
                && $context['community_id'] === $serviceRequest->community_id
                && $context['request_title'] === $serviceRequest->title);
    }

    public function test_notification_is_not_logged_outside_development(): void
    {
        $logger = Log::spy();

        (new SendRequestCreatedNotification)->handle(new RequestCreated(new ServiceRequest));

        $logger->shouldNotHaveReceived('info');
    }
}
