<?php

declare(strict_types=1);

namespace Tests\Feature\Communities;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\Community;
use App\Modules\Communities\Models\CommunityMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class CurrentCommunityTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['community'])]
    #[TestWith(['membership'])]
    #[TestWith(['role'])]
    public function test_unresolved_context_throws_a_clear_exception(string $accessor): void
    {
        $context = $this->app->make(CurrentCommunity::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The current community has not been resolved.');

        $context->{$accessor}();
    }

    public function test_context_exposes_the_selected_community_membership_and_role(): void
    {
        $membership = CommunityMember::factory()->manager()->create();
        $context = $this->app->make(CurrentCommunity::class);

        $context->set($membership->community, $membership);

        $this->assertTrue($context->isSet());
        $this->assertSame($membership->community, $context->community());
        $this->assertSame($membership, $context->membership());
        $this->assertSame(CommunityRole::Manager, $context->role());
    }

    public function test_context_rejects_a_membership_from_another_community(): void
    {
        $membership = CommunityMember::factory()->create();
        $otherCommunity = Community::factory()->create();
        $context = $this->app->make(CurrentCommunity::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The membership does not belong to the current community.');

        $context->set($otherCommunity, $membership);
    }

    #[TestWith(['community'])]
    #[TestWith(['membership'])]
    #[TestWith(['role'])]
    public function test_clearing_context_makes_previous_data_unavailable(string $accessor): void
    {
        $membership = CommunityMember::factory()->create();
        $context = $this->app->make(CurrentCommunity::class);
        $context->set($membership->community, $membership);

        $context->clear();

        $this->assertFalse($context->isSet());
        $this->expectException(LogicException::class);
        $context->{$accessor}();
    }

    public function test_container_shares_context_within_a_scope_but_not_between_scopes(): void
    {
        $membership = CommunityMember::factory()->create();
        $context = $this->app->make(CurrentCommunity::class);
        $context->set($membership->community, $membership);
        $this->assertSame($context, $this->app->make(CurrentCommunity::class));

        $this->app->forgetScopedInstances();

        $nextContext = $this->app->make(CurrentCommunity::class);
        $this->assertNotSame($context, $nextContext);
        $this->assertFalse($nextContext->isSet());
    }
}
