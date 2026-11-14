<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\Community;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Identity\Models\User;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Models\RequestMessage;
use App\Modules\Requests\Models\RequestStatusHistory;
use App\Modules\Requests\Models\ServiceRequest;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class RequestPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_factory_creates_a_resident_in_its_community(): void
    {
        $serviceRequest = ServiceRequest::factory()->create()->fresh();

        $this->assertModelExists($serviceRequest->community);
        $this->assertModelExists($serviceRequest->author);
        $this->assertSame(RequestStatus::New, $serviceRequest->status);
        $this->assertNull($serviceRequest->resolved_at);
        $this->assertDatabaseHas('community_members', [
            'community_id' => $serviceRequest->community_id,
            'user_id' => $serviceRequest->user_id,
            'role' => 'resident',
        ]);
        $this->assertDatabaseCount('communities', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('community_members', 1);
    }

    public function test_factory_reuses_explicit_relationships_and_existing_membership(): void
    {
        $membership = CommunityMember::factory()->resident()->create();
        $otherMembership = CommunityMember::factory()->for($membership->user)->manager()->create();

        $serviceRequests = ServiceRequest::factory()->count(2)
            ->for($membership->community)
            ->for($membership->user, 'author')
            ->create();

        foreach ($serviceRequests as $serviceRequest) {
            $this->assertTrue($serviceRequest->community->is($membership->community));
            $this->assertTrue($serviceRequest->author->is($membership->user));
        }
        $this->assertSame(CommunityRole::Resident, $membership->fresh()->role);
        $this->assertSame(CommunityRole::Manager, $otherMembership->fresh()->role);
        $this->assertDatabaseCount('communities', 2);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('community_members', 2);
    }

    public function test_factory_creates_membership_for_explicit_ids_without_extra_parents(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();

        $serviceRequest = ServiceRequest::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($serviceRequest->community->is($community));
        $this->assertTrue($serviceRequest->author->is($user));
        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => 'resident',
        ]);
        $this->assertDatabaseCount('communities', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('community_members', 1);
    }

    public function test_database_defaults_an_unspecified_status_to_new(): void
    {
        $membership = CommunityMember::factory()->resident()->create();
        $serviceRequest = ServiceRequest::factory()
            ->for($membership->community)
            ->for($membership->user, 'author')->make();
        unset($serviceRequest->status);

        $serviceRequest->save();

        $this->assertSame(RequestStatus::New, $serviceRequest->fresh()->status);
        $this->assertNull($serviceRequest->fresh()->resolved_at);
    }

    public function test_resolved_factory_state_persists_the_resolution_date(): void
    {
        $this->freezeSecond();

        $serviceRequest = ServiceRequest::factory()->resolved()->create()->fresh();

        $this->assertSame(RequestStatus::Resolved, $serviceRequest->status);
        $this->assertTrue($serviceRequest->resolved_at->equalTo(now()));
        $this->assertTrue($serviceRequest->created_at->equalTo(now()));
        $this->assertTrue($serviceRequest->updated_at->equalTo(now()));
        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'status' => 'resolved',
            'resolved_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_in_progress_factory_state_clears_the_resolution_date(): void
    {
        $serviceRequest = ServiceRequest::factory()->resolved()->inProgress()->create()->fresh();

        $this->assertSame(RequestStatus::InProgress, $serviceRequest->status);
        $this->assertNull($serviceRequest->resolved_at);
    }

    public function test_message_factory_uses_the_request_author_and_persists_both_relationships(): void
    {
        $this->freezeSecond();

        $message = RequestMessage::factory()->create()->fresh();

        $this->assertTrue($message->author->is($message->serviceRequest->author));
        $this->assertSame([$message->id], $message->serviceRequest->messages->modelKeys());
        $this->assertTrue($message->created_at->equalTo(now()));
        $this->assertTrue($message->updated_at->equalTo(now()));
        $this->assertDatabaseCount('service_requests', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('community_members', 1);
    }

    public function test_history_factory_persists_creation_without_an_updated_at_column(): void
    {
        $this->freezeSecond();

        $history = RequestStatusHistory::factory()->create()->fresh();

        $this->assertNull($history->from_status);
        $this->assertSame(RequestStatus::New, $history->to_status);
        $this->assertTrue($history->author->is($history->serviceRequest->author));
        $this->assertSame([$history->id], $history->serviceRequest->statusHistories->modelKeys());
        $this->assertTrue($history->created_at->equalTo(now()));
        $this->assertArrayNotHasKey('updated_at', $history->getAttributes());
        $this->assertDatabaseCount('service_requests', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_children_reuse_an_existing_request_and_explicit_manager_author(): void
    {
        $serviceRequest = ServiceRequest::factory()->inProgress()->create();
        $manager = CommunityMember::factory()->for($serviceRequest->community)->manager()->create();

        $message = RequestMessage::factory()
            ->for($serviceRequest)
            ->for($manager->user, 'author')->create();
        $history = RequestStatusHistory::factory()
            ->for($serviceRequest)
            ->for($manager->user, 'author')->create([
                'from_status' => RequestStatus::New,
                'to_status' => RequestStatus::InProgress,
            ]);

        $this->assertTrue($message->fresh()->serviceRequest->is($serviceRequest));
        $this->assertTrue($message->fresh()->author->is($manager->user));
        $this->assertTrue($history->fresh()->serviceRequest->is($serviceRequest));
        $this->assertTrue($history->fresh()->author->is($manager->user));
        $this->assertSame(RequestStatus::New, $history->fresh()->from_status);
        $this->assertSame(RequestStatus::InProgress, $history->fresh()->to_status);
        $this->assertDatabaseHas('request_status_histories', [
            'id' => $history->id,
            'from_status' => 'new',
            'to_status' => 'in_progress',
        ]);
        $this->assertDatabaseCount('communities', 1);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('service_requests', 1);
    }

    public function test_history_can_be_saved_without_an_author(): void
    {
        $history = RequestStatusHistory::factory()->create(['user_id' => null])->fresh();

        $this->assertNull($history->author);
        $this->assertNull($history->from_status);
        $this->assertSame(RequestStatus::New, $history->to_status);
        $this->assertDatabaseHas(
            'request_status_histories',
            ['id' => $history->id, 'user_id' => null],
        );
    }

    /** @param class-string<ServiceRequest|RequestMessage|RequestStatusHistory> $modelClass */
    #[TestWith([ServiceRequest::class, 'community_id'])]
    #[TestWith([ServiceRequest::class, 'user_id'])]
    #[TestWith([RequestMessage::class, 'service_request_id'])]
    #[TestWith([RequestMessage::class, 'user_id'])]
    #[TestWith([RequestStatusHistory::class, 'service_request_id'])]
    #[TestWith([RequestStatusHistory::class, 'user_id'])]
    public function test_rejects_records_with_missing_parents(
        string $modelClass,
        string $foreignKey,
    ): void {
        $record = $modelClass::factory()->make();
        $record->{$foreignKey} = 0;
        $originalCount = $modelClass::query()->count();

        $this->assertForeignKeyViolation(fn (): bool => $record->save());

        $this->assertDatabaseCount($record->getTable(), $originalCount);
    }

    public function test_deleting_an_appeal_removes_its_messages_and_history_only(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $message = RequestMessage::factory()->for($serviceRequest)->create();
        $history = RequestStatusHistory::factory()->for($serviceRequest)->create();
        $other = ServiceRequest::factory()->for($serviceRequest->community)->create();
        $otherMessage = RequestMessage::factory()->for($other)->create();
        $otherHistory = RequestStatusHistory::factory()->for($other)->create();

        $serviceRequest->delete();

        $this->assertModelMissing($message);
        $this->assertModelMissing($history);
        $this->assertModelExists($serviceRequest->author);
        $this->assertModelExists($serviceRequest->community);
        $this->assertModelExists($other);
        $this->assertModelExists($otherMessage);
        $this->assertModelExists($otherHistory);
    }

    public function test_deleting_a_community_removes_only_its_requests_and_their_children(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $message = RequestMessage::factory()->for($serviceRequest)->create();
        $history = RequestStatusHistory::factory()->for($serviceRequest)->create();
        $other = ServiceRequest::factory()
            ->for($serviceRequest->author, 'author')
            ->create();
        $otherMessage = RequestMessage::factory()->for($other)->create();
        $otherHistory = RequestStatusHistory::factory()->for($other)->create();

        $serviceRequest->community->delete();

        $this->assertModelMissing($serviceRequest);
        $this->assertModelMissing($message);
        $this->assertModelMissing($history);
        $this->assertModelExists($serviceRequest->author);
        $this->assertModelExists($other);
        $this->assertModelExists($otherMessage);
        $this->assertModelExists($otherHistory);
    }

    public function test_deleting_membership_preserves_the_request_and_its_discussion(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $message = RequestMessage::factory()->for($serviceRequest)->create();
        $history = RequestStatusHistory::factory()->for($serviceRequest)->create();

        $serviceRequest->community->communityMembers()
            ->where('user_id', $serviceRequest->user_id)
            ->delete();

        $this->assertModelExists($serviceRequest);
        $this->assertModelExists($message);
        $this->assertModelExists($history);
        $this->assertDatabaseCount('community_members', 0);
    }

    public function test_request_author_cannot_be_deleted_with_their_discussion(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $history = RequestStatusHistory::factory()->for($serviceRequest)->create();
        $author = $serviceRequest->author;

        $this->assertForeignKeyViolation(fn (): ?bool => $author->delete());

        $this->assertModelExists($author);
        $this->assertModelExists($serviceRequest);
        $this->assertSame($author->id, $history->fresh()->user_id);
    }

    public function test_comment_author_cannot_be_deleted_with_their_messages(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $manager = CommunityMember::factory()
            ->for($serviceRequest->community)
            ->manager()
            ->create();
        $message = RequestMessage::factory()
            ->for($serviceRequest)
            ->for($manager->user, 'author')
            ->create();

        $this->assertForeignKeyViolation(fn (): ?bool => $manager->user->delete());

        $this->assertModelExists($manager->user);
        $this->assertModelExists($message);
        $this->assertModelExists($serviceRequest);
    }

    public function test_deleting_a_history_author_preserves_the_event_with_a_null_author(): void
    {
        $serviceRequest = ServiceRequest::factory()->inProgress()->create();
        $manager = CommunityMember::factory()
            ->for($serviceRequest->community)
            ->manager()
            ->create();
        $history = RequestStatusHistory::factory()
            ->for($serviceRequest)
            ->for($manager->user, 'author')
            ->create([
                'from_status' => RequestStatus::New,
                'to_status' => RequestStatus::InProgress,
            ]);

        $manager->user->delete();

        $this->assertModelExists($history);
        $this->assertNull($history->fresh()->author);
        $this->assertDatabaseHas('request_status_histories', [
            'id' => $history->id,
            'user_id' => null,
            'from_status' => 'new',
            'to_status' => 'in_progress',
        ]);
        $this->assertSame(RequestStatus::InProgress, $serviceRequest->fresh()->status);
    }

    private function assertForeignKeyViolation(Closure $operation): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Expected a foreign key constraint violation.');
        } catch (QueryException $exception) {
            $this->assertSame('23503', $exception->getCode());
        }
    }
}
