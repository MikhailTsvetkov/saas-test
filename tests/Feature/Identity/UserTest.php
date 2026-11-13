<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_provider_loads_the_modular_user_created_by_the_factory(): void
    {
        $user = UserFactory::new()->create();

        $authenticatedUser = Auth::guard()->getProvider()->retrieveById($user->id);

        $this->assertInstanceOf(User::class, $authenticatedUser);
        $this->assertTrue($authenticatedUser->is($user));
    }

    public function test_user_hashes_passwords_and_hides_credentials_from_serialization(): void
    {
        $user = User::factory()->create(['password' => 'test-password']);

        $storedUser = $user->fresh();

        $this->assertTrue(Hash::check('test-password', $storedUser->password));
        $this->assertArrayNotHasKey('password', $storedUser->toArray());
        $this->assertArrayNotHasKey('remember_token', $storedUser->toArray());
    }

    public function test_demo_seeder_creates_users_in_the_identity_module(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas(
            'users',
            ['name' => 'Управляющий Соснового', 'email' => 'manager1@example.test'],
        );
    }
}
