<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\Community;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Identity\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_seeder_creates_two_communities_five_users_and_six_memberships(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('communities', 2);
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('community_members', 6);
        $this->assertDatabaseHas('communities', [
            'slug' => 'sosnovy',
            'name' => 'ЖК Сосновый',
        ]);
        $this->assertDatabaseHas('communities', [
            'slug' => 'lesnoy',
            'name' => 'Посёлок Лесной',
        ]);

        $memberships = CommunityMember::query()->with(['user', 'community'])->get();
        foreach ([
            ['sosnovy', 'manager1@example.test', CommunityRole::Manager],
            ['sosnovy', 'resident1@example.test', CommunityRole::Resident],
            ['sosnovy', 'multi@example.test', CommunityRole::Resident],
            ['lesnoy', 'manager2@example.test', CommunityRole::Manager],
            ['lesnoy', 'resident2@example.test', CommunityRole::Resident],
            ['lesnoy', 'multi@example.test', CommunityRole::Manager],
        ] as [$slug, $email, $role]) {
            $membership = $memberships->first(
                fn (CommunityMember $member): bool => $member->community->slug === $slug && $member->user->email === $email,
            );
            $this->assertNotNull($membership);
            $this->assertSame($role, $membership->role);
        }

        $hasher = $this->app->make(Hasher::class);
        foreach (User::all() as $user) {
            $this->assertNotSame('DemoPassword123!', $user->password);
            $this->assertTrue($hasher->check('DemoPassword123!', $user->password));
        }
    }

    public function test_repeated_seeding_preserves_records_ids_password_hashes_and_timestamps(): void
    {
        $this->freezeTime();
        $this->seed(DatabaseSeeder::class);
        $users = User::query()->orderBy('id')
            ->get()->map->getAttributes()->all();
        $communities = Community::query()->orderBy('id')
            ->get()->map->getAttributes()->all();
        $memberships = CommunityMember::query()->orderBy('id')
            ->get()->map->getAttributes()->all();
        $this->travel(1)->minutes();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($users, User::query()->orderBy('id')
            ->get()->map->getAttributes()->all());
        $this->assertSame($communities, Community::query()->orderBy('id')
            ->get()->map->getAttributes()->all());
        $this->assertSame($memberships, CommunityMember::query()->orderBy('id')
            ->get()->map->getAttributes()->all());
    }

    public function test_existing_profiles_passwords_and_unrelated_memberships_are_preserved(): void
    {
        $user = User::factory()->create([
            'email' => 'multi@example.test',
            'name' => 'Существующий пользователь',
            'password' => 'ExistingPassword123!',
        ]);
        $sosnovy = Community::factory()->create(['slug' => 'sosnovy']);
        $additionalMember = CommunityMember::factory()->manager()->for($sosnovy)->create();
        $additionalCommunity = CommunityMember::factory()->manager()->for($user)->create();
        $originalUser = $user->fresh()->getAttributes();
        $originalMember = $additionalMember->fresh()->getAttributes();
        $originalCommunity = $additionalCommunity->community->getAttributes();
        $originalOtherUser = $additionalMember->user->getAttributes();
        $originalMembership = $additionalCommunity->fresh()->getAttributes();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 6);
        $this->assertDatabaseCount('communities', 3);
        $this->assertDatabaseCount('community_members', 8);
        $this->assertSame($originalUser, $user->fresh()->getAttributes());
        $this->assertSame($originalOtherUser, $additionalMember->user->fresh()->getAttributes());
        $this->assertSame($originalMember, $additionalMember->fresh()->getAttributes());
        $this->assertSame($originalMembership, $additionalCommunity->fresh()->getAttributes());
        $this->assertSame($originalCommunity, $additionalCommunity->community->fresh()->getAttributes());
    }

    public function test_repeated_seeding_restores_demo_community_details_and_roles(): void
    {
        $this->seed(DatabaseSeeder::class);
        $community = Community::query()->where('slug', 'sosnovy')->firstOrFail();
        $originalDescription = $community->description;
        $user = User::query()->where('email', 'manager1@example.test')->firstOrFail();
        $membership = $community->communityMembers()->whereBelongsTo($user)->firstOrFail();
        $community->update(['name' => 'Изменённое имя', 'description' => null]);
        $membership->update(['role' => CommunityRole::Resident]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('ЖК Сосновый', $community->fresh()->name);
        $this->assertSame($originalDescription, $community->fresh()->description);
        $this->assertSame(CommunityRole::Manager, $membership->fresh()->role);
        $this->assertDatabaseCount('communities', 2);
        $this->assertDatabaseCount('community_members', 6);
    }

    /**
     * @param  list<string>  $slugs
     * @param  list<string>  $roles
     */
    #[TestWith(['manager1@example.test', ['sosnovy'], ['manager']])]
    #[TestWith(['resident1@example.test', ['sosnovy'], ['resident']])]
    #[TestWith(['manager2@example.test', ['lesnoy'], ['manager']])]
    #[TestWith(['resident2@example.test', ['lesnoy'], ['resident']])]
    #[TestWith(['multi@example.test', ['sosnovy', 'lesnoy'], ['resident', 'manager']])]
    public function test_demo_accounts_can_log_in_and_see_their_communities(
        string $email,
        array $slugs,
        array $roles,
    ): void {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'email' => $email,
            'password' => 'DemoPassword123!',
        ])->assertRedirectToRoute('home')->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs(User::query()->where('email', $email)->firstOrFail());
        $this->get(route('home'))->assertRedirectToRoute('communities.index');
        $this->get(route('communities.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Communities/Index')
                ->where('auth.user.email', $email)
                ->where('community', null)
                ->where('communities', fn (Collection $communities): bool => $communities->pluck('slug')->all() === $slugs
                    && $communities->pluck('role')->all() === $roles));
    }

    public function test_multi_account_has_different_access_in_each_demo_community(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->post(route('login.store'), [
            'email' => 'multi@example.test',
            'password' => 'DemoPassword123!',
        ])->assertRedirectToRoute('home');

        $this->get(route('communities.dashboard', 'sosnovy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('community.role', 'resident')
                ->where('urls.members', null));
        $this->get(route('communities.members', 'sosnovy'))
            ->assertForbidden();
        $this->get(route('communities.dashboard', 'lesnoy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('community.role', 'manager'));
        $this->get(route('communities.members', 'lesnoy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('community.slug', 'lesnoy')
                ->where('members', fn (Collection $members): bool => $members->pluck('email')->sort()->values()->all() === [
                    'manager2@example.test', 'multi@example.test', 'resident2@example.test',
                ]));

        $this->post(route('logout'))
            ->assertRedirectToRoute('login');
        $this->assertGuest();
        $this->get(route('communities.index'))
            ->assertRedirectToRoute('login');
        $this->get(route('communities.dashboard', 'lesnoy'))
            ->assertRedirectToRoute('login');
        $this->get(route('communities.members', 'lesnoy'))
            ->assertRedirectToRoute('login');
    }
}
