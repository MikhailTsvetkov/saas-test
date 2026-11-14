<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class RequestIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_resident_sees_only_own_requests_in_current_community(): void
    {
        $this->freezeSecond();
        $own = ServiceRequest::factory()->create();
        ServiceRequest::factory()->for($own->community)->create();
        ServiceRequest::factory()->for($own->author, 'author')->create();

        $this->actingAs($own->author)->get(route(
            'communities.requests.index',
            $own->community->slug,
        ))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Requests/Index')
            ->where('requests.total', 1)
            ->where('requests.data', [[
                'id' => $own->id, 'title' => $own->title, 'status' => 'new',
                'created_at' => now()->toISOString(), 'updated_at' => now()->toISOString(),
                'author' => null,
                'showUrl' => route('communities.requests.show', [
                    'community' => $own->community->slug, 'request' => $own->id,
                ]),
            ]])
            ->where('filters.status', null)
            ->where('createUrl', route(
                'communities.requests.create',
                $own->community->slug,
            )));
    }

    public function test_manager_sees_all_current_community_requests_with_safe_authors(): void
    {
        $manager = CommunityMember::factory()->manager()->create();
        $ownCommunity = ServiceRequest::factory()->count(2)->for($manager->community)->create();
        ServiceRequest::factory()->create();

        $response = $this->actingAs($manager->user)->get(route(
            'communities.requests.index',
            $manager->community->slug,
        ))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('requests.total', 2)->where('createUrl', null)
            ->where(
                'requests.data',
                fn (Collection $data): bool => $data
                        ->pluck('id')
                        ->sort()
                        ->values()
                        ->all() === $ownCommunity->modelKeys(),
            ));

        foreach ($response->viewData('page')['props']['requests']['data'] as $item) {
            $serviceRequest = $ownCommunity->firstWhere('id', $item['id']);
            $this->assertSame(
                [
                    'id' => $serviceRequest->author->id,
                    'name' => $serviceRequest->author->name,
                ],
                $item['author'],
            );
            $this->assertArrayNotHasKey('description', $item);
            $this->assertArrayNotHasKey('statusHistories', $item);
        }
    }

    #[TestWith(['new'])]
    #[TestWith(['in_progress'])]
    #[TestWith(['resolved'])]
    public function test_status_filter_is_applied_on_the_server(string $status): void
    {
        $manager = CommunityMember::factory()->manager()->create();
        ServiceRequest::factory()->for($manager->community)->create();
        ServiceRequest::factory()->for($manager->community)->inProgress()->create();
        ServiceRequest::factory()->for($manager->community)->resolved()->create();

        $this->actingAs($manager->user)->get(route('communities.requests.index', [
            'community' => $manager->community->slug, 'status' => $status,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('requests.total', 1)
            ->where('requests.data.0.status', $status)
            ->where('filters.status', $status));
    }

    public function test_pagination_preserves_status_and_tenant_without_untrusted_query_parameters(): void
    {
        $this->freezeSecond();
        $manager = CommunityMember::factory()->manager()->create();
        $requests = ServiceRequest::factory()
            ->count(16)
            ->for($manager->community)
            ->inProgress()
            ->create();
        $latest = $requests->first();
        $latest->updated_at = now()->addMinute();
        $latest->save();
        ServiceRequest::factory()->for($manager->community)->resolved()->create();
        $index = route(
            'communities.requests.index',
            $manager->community->slug,
        );

        $response = $this->actingAs($manager->user)
            ->get($index.'?status=in_progress&role=manager&user_id=999')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('requests.total', 16)
                ->where('requests.per_page', 15)
                ->where('requests.current_page', 1)
                ->where('requests.data.0.id', $latest->id)
                ->where('requests.data.1.id', $requests->last()->id)
                ->where('requests.next_page_url', $index . '?status=in_progress&page=2'));
        $nextUrl = $response->viewData('page')['props']['requests']['next_page_url'];

        $this->get($nextUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('requests.data', 1)->where('requests.current_page', 2)
            ->where('requests.data.0.id', $requests[1]->id)
            ->where('requests.next_page_url', null)
            ->where('requests.prev_page_url', $index . '?status=in_progress&page=1'));
        $this->get($index.'?status=in_progress&page=3')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('requests.data', 0)
                ->where('requests.total', 16));
    }

    #[TestWith(['status', 'unknown', 'Выберите существующий статус.'])]
    #[TestWith(['status', ['new'], 'Выберите существующий статус.'])]
    #[TestWith(['page', 'bad', 'Номер страницы должен быть целым числом.'])]
    #[TestWith(['page', '0', 'Номер страницы должен быть не меньше 1.'])]
    #[TestWith(['page', '2147483648', 'Номер страницы слишком большой.'])]
    public function test_invalid_filters_and_pages_are_rejected(
        string $field,
        mixed $value,
        string $message,
    ): void {
        $member = CommunityMember::factory()->resident()->create();

        $this->actingAs($member->user)->getJson(route('communities.requests.index', [
            'community' => $member->community->slug, $field => $value,
        ]))->assertUnprocessable()->assertJsonValidationErrors([$field])
            ->assertJsonPath('errors.' . $field . '.0', $message);
    }

    public function test_empty_list_and_cleared_filter_are_supported(): void
    {
        $member = CommunityMember::factory()->resident()->create();

        $this->actingAs($member->user)->get(route('communities.requests.index', [
            'community' => $member->community->slug, 'status' => '',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', null)
            ->where('requests.total', 0)
            ->has('requests.data', 0));
    }

    public function test_author_queries_do_not_grow_with_the_number_of_requests(): void
    {
        $manager = CommunityMember::factory()->manager()->create();
        ServiceRequest::factory()->for($manager->community)->create();
        $this->actingAs($manager->user);
        $url = route(
            'communities.requests.index',
            $manager->community->slug,
        );
        DB::enableQueryLog();

        try {
            $this->get($url)->assertOk();
            $initial = collect(DB::getQueryLog())
                ->filter(fn (array $query): bool => str_contains($query['query'], 'from "users"'))->count();
            ServiceRequest::factory()->count(7)->for($manager->community)->create();
            DB::flushQueryLog();
            $this->get($url)->assertOk();
            $expanded = collect(DB::getQueryLog())
                ->filter(fn (array $query): bool => str_contains($query['query'], 'from "users"'))->count();

            $this->assertGreaterThan(0, $initial);
            $this->assertSame($initial, $expanded);
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
