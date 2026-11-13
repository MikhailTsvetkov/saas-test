<?php

declare(strict_types=1);

namespace Tests\Feature\Inertia;

use App\Modules\Communities\Models\CommunityMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class SharedPropsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_guest_receives_null_user_and_community(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('auth.user', null)
                ->where('community', null)
                ->where('urls.login', route('login'))
                ->where('urls.loginStore', route('login.store'))
                ->where('urls.logout', route('logout')));
    }

    public function test_only_public_user_fields_are_shared_even_when_memberships_are_loaded(): void
    {
        $membership = CommunityMember::factory()->manager()->create();
        $user = $membership->user->load('communityMembers.community', 'communities');

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->where('auth.user', [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->where('community', null)
                ->missing('communities')
                ->missing('communityMembers'));
    }

    public function test_login_validation_errors_remain_available_to_inertia(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'))
            ->assertRedirectToRoute('login');

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('errors.email', 'Введите email.')
                ->where('errors.password', 'Введите пароль.')
                ->where('auth.user', null));
    }
}
