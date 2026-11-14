<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\Community;
use App\Modules\Identity\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(ConnectionInterface $database): void
    {
        $database->transaction(function (): void {
            $users = [];

            foreach ([
                'manager1@example.test' => 'Управляющий Соснового',
                'resident1@example.test' => 'Житель Соснового',
                'manager2@example.test' => 'Управляющий Лесного',
                'resident2@example.test' => 'Житель Лесного',
                'multi@example.test' => 'Алексей — участник двух сообществ',
            ] as $email => $name) {
                $users[$email] = User::query()->firstOrCreate(
                    ['email' => $email],
                    ['name' => $name, 'password' => 'DemoPassword123!'],
                );
            }

            foreach ([
                'sosnovy' => [
                    'name' => 'ЖК Сосновый',
                    'description' => 'Сообщество жителей жилого комплекса «Сосновый».',
                    'members' => [
                        'manager1@example.test' => CommunityRole::Manager,
                        'resident1@example.test' => CommunityRole::Resident,
                        'multi@example.test' => CommunityRole::Resident,
                    ],
                ],
                'lesnoy' => [
                    'name' => 'Посёлок Лесной',
                    'description' => 'Сообщество жителей посёлка «Лесной».',
                    'members' => [
                        'manager2@example.test' => CommunityRole::Manager,
                        'resident2@example.test' => CommunityRole::Resident,
                        'multi@example.test' => CommunityRole::Manager,
                    ],
                ],
            ] as $slug => $data) {
                $community = Community::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $data['name'], 'description' => $data['description']],
                );

                foreach ($data['members'] as $email => $role) {
                    $community->communityMembers()->updateOrCreate(
                        ['user_id' => $users[$email]->id],
                        ['role' => $role],
                    );
                }
            }

            $this->call(RequestDemoSeeder::class);
        });
    }
}
