<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Communities\Models\CommunityMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirectToRoute('login');
    }

    public function test_root_redirects_authenticated_users_to_the_list_even_with_one_community(): void
    {
        $membership = CommunityMember::factory()->create();

        $this->actingAs($membership->user)
            ->get('/')
            ->assertRedirectToRoute('communities.index');
    }
}
