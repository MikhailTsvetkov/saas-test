<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_guest_can_view_the_login_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_login_regenerates_the_session_and_persists_authentication(): void
    {
        $user = User::factory()->create();
        $this->withSession(['before_login' => 'preserved']);
        $oldId = session()->getId();
        $oldToken = session()->token();

        $this->post(
            route('login.store'),
            ['email' => $user->email, 'password' => 'password'],
        )
            ->assertRedirectToRoute('home')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('before_login', 'preserved');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());

        $this->withCookie(config('session.cookie'), session()->getId());
        Auth::forgetGuards();
        $this->get(route('communities.index'))->assertInertia(
            fn (Assert $page) => $page->where('auth.user.id', $user->id),
        );
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_invalid_credentials_do_not_reveal_whether_an_account_exists(
        bool $accountExists,
    ): void {
        $email = 'resident@example.test';
        if ($accountExists) {
            User::factory()->create(['email' => $email]);
        }

        $this->from(route('login'))->post(
            route('login.store'),
            ['email' => $email, 'password' => 'incorrect'],
        )
            ->assertRedirectToRoute('login')
            ->assertSessionHasErrors(['email' => 'Не удалось войти. Проверьте email и пароль.'])
            ->assertSessionMissingInput('password');

        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'))
            ->assertRedirectToRoute('login')
            ->assertSessionHasErrors(['email' => 'Введите email.', 'password' => 'Введите пароль.']);

        $this->assertGuest();
    }

    #[TestWith(['email', 'invalid-email', 'Введите корректный email.'])]
    #[TestWith(['email', ['resident@example.test'], 'Введите корректный email.'])]
    #[TestWith(['password', ['password'], 'Введите корректный пароль.'])]
    public function test_login_rejects_invalid_field_values(
        string $field,
        mixed $value,
        string $message,
    ): void {
        $credentials = ['email' => 'resident@example.test', 'password' => 'password'];
        $credentials[$field] = $value;

        $this->from(route('login'))
            ->post(route('login.store'), $credentials)
            ->assertRedirectToRoute('login')
            ->assertSessionHasErrors([$field => $message]);

        $this->assertGuest();
    }

    public function test_login_rejects_email_longer_than_255_characters(): void
    {
        $email = str_repeat('a', 245) . '@example.test';

        $this->from(route('login'))
            ->post(
                route('login.store'),
                ['email' => $email, 'password' => 'password'],
            )
            ->assertRedirectToRoute('login')
            ->assertSessionHasErrors(['email' => 'Email не должен превышать 255 символов.']);
    }

    public function test_login_ignores_untrusted_user_community_and_role_fields(): void
    {
        $membership = CommunityMember::factory()->resident()->create();
        $otherMembership = CommunityMember::factory()->manager()->create();

        $this->post(route('login.store'), [
            'email' => $membership->user->email,
            'password' => 'password',
            'user_id' => $otherMembership->user_id,
            'community_id' => $otherMembership->community_id,
            'role' => 'manager',
        ])->assertRedirectToRoute('home');

        $this->assertAuthenticatedAs($membership->user);
        $this->assertSame(CommunityRole::Resident, $membership->fresh()->role);
        $this->assertDatabaseMissing('community_members', [
            'user_id' => $membership->user_id,
            'community_id' => $otherMembership->community_id,
        ]);
        $this->assertFalse(session()->has('community_id'));
    }

    public function test_login_attempts_are_limited_by_normalized_email_and_ip_until_the_limit_expires(): void
    {
        $user = User::factory()->create(['email' => 'resident@example.test']);
        $this->freezeTime();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'email' => strtoupper($user->email),
                    'password' => 'incorrect',
                ])
                ->assertSessionHasErrors(['email' => 'Не удалось войти. Проверьте email и пароль.']);
        }

        $this->post(
            route('login.store'),
            ['email' => $user->email, 'password' => 'password'],
        )
            ->assertRedirectToRoute('login')
            ->assertHeader('Retry-After')
            ->assertSessionHasErrors(['email' => 'Слишком много попыток входа. Подождите минуту и попробуйте снова.'])
            ->assertSessionMissingInput('password');
        $this->assertGuest();

        $this->travel(61)->seconds();
        $this->post(
            route('login.store'),
            ['email' => $user->email, 'password' => 'password'],
        )
            ->assertRedirectToRoute('home');
        $this->assertAuthenticatedAs($user);
    }

    #[TestWith(['email'])]
    #[TestWith(['ip'])]
    public function test_login_limit_does_not_block_a_different_email_or_ip(
        string $difference,
    ): void {
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'email' => $user->email,
                    'password' => 'incorrect',
                ]);
        }

        if ($difference === 'email') {
            $user = User::factory()->create();
        } else {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1']);
        }

        $this->post(
            route('login.store'),
            ['email' => $user->email, 'password' => 'password'],
        )->assertRedirectToRoute('home');

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_users_are_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('login'))->assertRedirectToRoute('home');
        $this->post(route('login.store'))
            ->assertRedirectToRoute('home');
        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_invalidates_the_session_and_regenerates_the_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['private_marker' => 'remove-me']);
        $oldId = session()->getId();
        $oldToken = session()->token();

        $this->post(route('logout'))
            ->assertRedirectToRoute('login')
            ->assertSessionMissing('private_marker');

        $this->assertGuest();
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());

        $this->withCookie(config('session.cookie'), session()->getId());
        Auth::forgetGuards();
        $this->post(route('logout'))
            ->assertRedirectToRoute('login');
        $this->get(route('home'))->assertRedirectToRoute('login');
        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
    }

    public function test_guest_is_redirected_to_login_when_trying_to_logout(): void
    {
        $this->post(route('logout'))
            ->assertRedirectToRoute('login');
    }

    public function test_logout_cannot_be_triggered_by_get(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('logout'))
            ->assertMethodNotAllowed();

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_requires_a_valid_csrf_token_when_origin_verification_is_unavailable(): void
    {
        $user = User::factory()->create();
        $this->enableCsrfProtection();

        $this->post(
            route('login.store'),
            ['email' => $user->email, 'password' => 'password'],
        )
            ->assertStatus(419);

        $this->assertGuest();
    }

    public function test_logout_rejects_a_mismatched_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->enableCsrfProtection();

        $this->actingAs($user)->withSession(['_token' => 'session-token'])
            ->post(
                route('logout'),
                ['_token' => 'incorrect-token'],
            )->assertStatus(419);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_and_logout_accept_valid_csrf_tokens(): void
    {
        $user = User::factory()->create();
        $this->enableCsrfProtection();

        $this->withSession(['_token' => 'login-token'])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
                '_token' => 'login-token',
            ])->assertRedirectToRoute('home');
        $this->assertAuthenticatedAs($user);

        $this->withCookie(config('session.cookie'), session()->getId())
            ->post(
                route('logout'),
                ['_token' => session()->token()],
            )
            ->assertRedirectToRoute('login');
        $this->assertGuest();
    }

    private function enableCsrfProtection(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn () => new class(
            $this->app,
            $this->app['encrypter'],
        ) extends PreventRequestForgery {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }
}
