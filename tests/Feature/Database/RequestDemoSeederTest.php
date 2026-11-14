<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Models\RequestStatusHistory;
use App\Modules\Requests\Models\ServiceRequest;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RequestDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_requests_are_created_in_both_communities_with_expected_status_history(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('service_requests', 6);

        $sosnovyRequests = ServiceRequest::query()
            ->whereHas('community', fn ($query): mixed => $query->where('slug', 'sosnovy'))
            ->orderBy('id')->get();
        $lesnoyRequests = ServiceRequest::query()
            ->whereHas('community', fn ($query): mixed => $query->where('slug', 'lesnoy'))
            ->orderBy('id')->get();

        $this->assertSame([
            'Не работает освещение в подъезде',
            'Протечка трубы в подвале',
            'Сломан домофон',
        ], $sosnovyRequests->pluck('title')->all());
        $this->assertSame([
            'Не работает уличный фонарь',
            'Повреждено дорожное покрытие',
            'Не открывается калитка',
        ], $lesnoyRequests->pluck('title')->all());

        $this->assertSame([
            RequestStatus::New,
            RequestStatus::InProgress,
            RequestStatus::Resolved,
        ], $sosnovyRequests->pluck('status')->all());
        $this->assertSame([
            RequestStatus::New,
            RequestStatus::InProgress,
            RequestStatus::Resolved,
        ], $lesnoyRequests->pluck('status')->all());

        $this->assertSame('multi@example.test', $sosnovyRequests[0]->author->email);
        $this->assertSame('resident1@example.test', $sosnovyRequests[1]->author->email);
        $this->assertSame('resident1@example.test', $sosnovyRequests[2]->author->email);
        $this->assertSame('resident2@example.test', $lesnoyRequests[0]->author->email);
        $this->assertSame('resident2@example.test', $lesnoyRequests[1]->author->email);
        $this->assertSame('resident2@example.test', $lesnoyRequests[2]->author->email);

        foreach ([
            'Не работает освещение в подъезде' => [RequestStatus::New],
            'Протечка трубы в подвале' => [RequestStatus::New, RequestStatus::InProgress],
            'Сломан домофон' => [RequestStatus::New, RequestStatus::InProgress, RequestStatus::Resolved],
            'Не работает уличный фонарь' => [RequestStatus::New],
            'Повреждено дорожное покрытие' => [RequestStatus::New, RequestStatus::InProgress],
            'Не открывается калитка' => [RequestStatus::New, RequestStatus::InProgress, RequestStatus::Resolved],
        ] as $title => $expected) {
            $request = ServiceRequest::query()->where('title', $title)->firstOrFail();
            $history = $request->statusHistories()->orderBy('created_at')->pluck('to_status')->all();
            $this->assertSame(
                array_map(fn (RequestStatus $status): string => $status->value, $expected),
                array_map(fn (RequestStatus|string $status): string => $status instanceof RequestStatus
                    ? $status->value
                    : $status, $history),
            );
        }

        $this->assertDatabaseHas('request_status_histories', [
            'from_status' => null,
            'to_status' => 'new',
        ]);
        $this->assertDatabaseHas('request_status_histories', [
            'from_status' => 'new',
            'to_status' => 'in_progress',
        ]);
        $this->assertDatabaseHas('request_status_histories', [
            'from_status' => 'in_progress',
            'to_status' => 'resolved',
        ]);
    }

    public function test_demo_requests_are_idempotent_on_repeated_seeding(): void
    {
        $this->seed(DatabaseSeeder::class);

        $requestIds = ServiceRequest::query()
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $historyIds = RequestStatusHistory::query()
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($requestIds, ServiceRequest::query()
            ->orderBy('id')
            ->pluck('id')
            ->all());
        $this->assertSame($historyIds, RequestStatusHistory::query()
            ->orderBy('id')
            ->pluck('id')
            ->all());
        $this->assertDatabaseCount('service_requests', 6);
        $this->assertDatabaseCount('request_status_histories', 12);
    }
}
