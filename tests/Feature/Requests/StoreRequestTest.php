<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Requests\Actions\CreateRequest;
use App\Modules\Requests\Models\RequestStatusHistory;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_resident_creates_an_appeal_with_history_and_a_one_time_confirmation(): void
    {
        $this->freezeSecond();
        $member = CommunityMember::factory()->resident()->create();
        $foreign = CommunityMember::factory()->manager()->create();
        $storeUrl = route(
            'communities.requests.store',
            $member->community->slug,
        );
        $this->actingAs($member->user)->get(route(
            'communities.requests.create',
            $member->community->slug,
        ))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Requests/Create')->where('storeUrl', $storeUrl));

        $response = $this->post($storeUrl, [
            'title' => 'Не горит свет',
            'description' => 'В подъезде не горит свет на первом этаже.',
            'community_id' => $foreign->community_id,
            'user_id' => $foreign->user_id,
            'role' => 'manager',
            'status' => 'resolved',
            'resolved_at' => now()->toISOString(),
        ]);

        $serviceRequest = ServiceRequest::query()->sole();
        $response->assertRedirectToRoute('communities.requests.show', [
            'community' => $member->community->slug, 'request' => $serviceRequest->id,
        ])->assertSessionHasNoErrors()->assertInertiaFlash('success', 'Обращение создано.');
        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id, 'community_id' => $member->community_id,
            'user_id' => $member->user_id, 'title' => 'Не горит свет',
            'description' => 'В подъезде не горит свет на первом этаже.',
            'status' => 'new', 'resolved_at' => null,
        ]);
        $this->assertDatabaseCount('request_status_histories', 1);
        $this->assertDatabaseHas('request_status_histories', [
            'service_request_id' => $serviceRequest->id, 'user_id' => $member->user_id,
            'from_status' => null, 'to_status' => 'new', 'created_at' => now()->toDateTimeString(),
        ]);
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->hasFlash('success', 'Обращение создано.'));
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->missingFlash('success'));
    }

    public function test_manager_cannot_open_creation_or_submit_for_a_resident(): void
    {
        $member = CommunityMember::factory()->manager()->create();
        $resident = CommunityMember::factory()->for($member->community)->resident()->create();

        $this->actingAs($member->user)->get(route(
            'communities.requests.create',
            $member->community->slug,
        ))
            ->assertForbidden();
        $this->post(route(
            'communities.requests.store',
            $member->community->slug,
        ), [
            'title' => 'Не горит свет',
            'description' => 'Подробное описание проблемы.',
            'user_id' => $resident->user_id,
        ])->assertForbidden();
        $this->assertDatabaseCount('service_requests', 0);
        $this->assertDatabaseCount('request_status_histories', 0);
    }

    public function test_missing_fields_return_russian_errors_without_saving(): void
    {
        $member = CommunityMember::factory()->resident()->create();
        $createUrl = route(
            'communities.requests.create',
            $member->community->slug,
        );

        $this->actingAs($member->user)->get($createUrl)->assertOk();
        $this->from($createUrl)
            ->post(route(
                'communities.requests.store',
                $member->community->slug,
            ))
            ->assertRedirect($createUrl)->assertSessionHasErrors([
                'title' => 'Введите заголовок.',
                'description' => 'Введите описание.',
            ]);
        $this->withCookie(config('session.cookie'), $this->app['session']->getId())
            ->get($createUrl)->assertInertia(fn (Assert $page) => $page
                ->where('errors.title', 'Введите заголовок.')
                ->where('errors.description', 'Введите описание.'));
        $this->assertDatabaseCount('service_requests', 0);
        $this->assertDatabaseCount('request_status_histories', 0);
    }

    /** @return iterable<string, array{string, mixed, string}> */
    public static function invalidFields(): iterable
    {
        yield 'short title' => ['title', '1234', 'Заголовок должен содержать не менее 5 символов.'];
        yield 'long title' => ['title', str_repeat('я', 256), 'Заголовок не должен превышать 255 символов.'];
        yield 'title array' => ['title', ['bad'], 'Заголовок должен быть строкой.'];
        yield 'blank title' => ['title', '   ', 'Введите заголовок.'];
        yield 'short description' => ['description', '123456789', 'Описание должно содержать не менее 10 символов.'];
        yield 'long description' => ['description', str_repeat('я', 5001), 'Описание не должно превышать 5000 символов.'];
        yield 'description array' => ['description', ['bad'], 'Описание должно быть строкой.'];
        yield 'blank description' => ['description', '   ', 'Введите описание.'];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_fields_do_not_create_an_appeal(
        string $field,
        mixed $value,
        string $message,
    ): void {
        $member = CommunityMember::factory()->resident()->create();

        $this->actingAs($member->user)->post(route(
            'communities.requests.store',
            $member->community->slug,
        ), [
            'title' => 'Не горит свет',
            'description' => 'Подробное описание проблемы.',
            $field => $value,
        ])->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('service_requests', 0);
        $this->assertDatabaseCount('request_status_histories', 0);
    }

    public function test_minimum_and_maximum_text_lengths_are_accepted(): void
    {
        $member = CommunityMember::factory()->resident()->create();
        $this->actingAs($member->user);

        foreach ([[5, 10], [255, 5000]] as [$titleLength, $descriptionLength]) {
            $this->post(route(
                'communities.requests.store',
                $member->community->slug,
            ), [
                'title' => str_repeat('я', $titleLength),
                'description' => str_repeat('я', $descriptionLength),
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('service_requests', 2);
        $this->assertDatabaseCount('request_status_histories', 2);
    }

    public function test_history_failure_rolls_back_the_request(): void
    {
        $member = CommunityMember::factory()->resident()->create();
        $event = 'eloquent.creating: '.RequestStatusHistory::class;
        Event::listen($event, function (): never {
            throw new RuntimeException('Simulated history write failure.');
        });

        try {
            $this->actingAs($member->user)->post(route(
                'communities.requests.store',
                $member->community->slug,
            ), [
                'title' => 'Не горит свет',
                'description' => 'Подробное описание проблемы.',
            ])->assertServerError();
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseCount('service_requests', 0);
        $this->assertDatabaseCount('request_status_histories', 0);
    }

    public function test_direct_creation_cannot_use_another_users_context(): void
    {
        $member = CommunityMember::factory()->resident()->create();
        $other = CommunityMember::factory()->resident()->for($member->community)->create();
        $this->app->make(CurrentCommunity::class)->set($member->community, $member);

        try {
            $this->app->make(CreateRequest::class)
                ->handle(
                    $other->user,
                    'Не горит свет',
                    'Подробное описание проблемы.',
                );
            $this->fail('An unrelated actor must not use the current context.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(404, $exception->status());
        }

        $this->assertDatabaseCount('service_requests', 0);
        $this->assertDatabaseCount('request_status_histories', 0);
    }
}
