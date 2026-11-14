<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Identity\Models\User;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class ServiceRequestPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['resident', true, true, false])]
    #[TestWith(['resident', false, false, false])]
    #[TestWith(['manager', true, true, true])]
    #[TestWith(['manager', false, true, true])]
    public function test_permissions_follow_membership_role_and_request_ownership(
        string $role, bool $ownsRequest, bool $canView, bool $canChangeStatus,
    ): void {
        $member = CommunityMember::factory()->create(['role' => $role]);
        $author = $ownsRequest ? $member->user : User::factory()->create();
        $serviceRequest = ServiceRequest::factory()
            ->for($member->community)
            ->for($author, 'author')
            ->create();
        $this->app->make(CurrentCommunity::class)->set($member->community, $member);
        $gate = Gate::forUser($member->user);

        $this->assertTrue($gate->allows('viewAny', ServiceRequest::class));
        $this->assertSame(
            $role === 'resident',
            $gate->allows('create', ServiceRequest::class),
        );
        $this->assertSame($canView, $gate->allows('view', $serviceRequest));
        $this->assertSame($canView, $gate->allows('addMessage', $serviceRequest));
        $this->assertSame(
            $canChangeStatus,
            $gate->allows('changeStatus', $serviceRequest),
        );
    }

    #[TestWith(['view'])]
    #[TestWith(['addMessage'])]
    #[TestWith(['changeStatus'])]
    public function test_foreign_request_is_hidden_even_for_a_manager_of_both_communities(
        string $ability,
    ): void {
        $member = CommunityMember::factory()->manager()->create();
        $other = CommunityMember::factory()->manager()->for($member->user)->create();
        $serviceRequest = ServiceRequest::factory()->for($other->community)->create();
        $this->app->make(CurrentCommunity::class)->set($member->community, $member);

        $response = Gate::forUser($member->user)->inspect($ability, $serviceRequest);

        $this->assertFalse($response->allowed());
        $this->assertSame(404, $response->status());
    }

    public function test_context_cannot_authorize_a_different_user_or_a_deleted_membership(): void
    {
        $member = CommunityMember::factory()->manager()->create();
        $other = CommunityMember::factory()->manager()->for($member->community)->create();
        $context = $this->app->make(CurrentCommunity::class);
        $context->set($member->community, $member);

        $this->assertSame(404, Gate::forUser($other->user)
            ->inspect('viewAny', ServiceRequest::class)
            ->status());
        $member->delete();
        $this->assertSame(404, Gate::forUser($member->user)
            ->inspect('viewAny', ServiceRequest::class)
            ->status());
    }

    public function test_policy_uses_fresh_role_and_requires_resolved_context(): void
    {
        $member = CommunityMember::factory()->manager()->create();
        $serviceRequest = ServiceRequest::factory()->for($member->community)->create();
        $context = $this->app->make(CurrentCommunity::class);
        $context->set($member->community, $member);
        $gate = Gate::forUser($member->user);
        CommunityMember::query()
            ->whereKey($member->id)
            ->update(['role' => CommunityRole::Resident]);

        $this->assertFalse($gate->allows('changeStatus', $serviceRequest));
        $this->assertFalse($gate->allows('view', $serviceRequest));
        $this->assertTrue($gate->allows('create', ServiceRequest::class));
        $context->clear();
        $this->assertSame(
            404,
            $gate->inspect('create', ServiceRequest::class)->status(),
        );
    }
}
