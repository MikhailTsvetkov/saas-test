<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Communities\Models\Community;
use App\Modules\Identity\Models\User;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Models\RequestMessage;
use App\Modules\Requests\Models\RequestStatusHistory;
use App\Modules\Requests\Models\ServiceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

final class RequestDemoSeeder extends Seeder
{
    /**
     * @return array<string, list<array{title: string, description: string, author: string, status: RequestStatus, resolved_at: \DateTimeInterface|null, created_at: string, updated_at: string, messages: list<array{author: string, body: string, created_at: string, updated_at: string}>, history: list<array{user: ?string, from: ?RequestStatus, to: RequestStatus, created_at: string}>}>>
     */
    private function definitions(): array
    {
        return [
            'sosnovy' => [
                [
                    'title' => 'Не работает освещение в подъезде',
                    'description' => 'В подъезде на первом этаже не горит освещение в коридоре и рядом с лифтовой площадкой.',
                    'author' => 'multi@example.test',
                    'status' => RequestStatus::New,
                    'resolved_at' => null,
                    'created_at' => '2026-11-14 09:15:00',
                    'updated_at' => '2026-11-14 09:15:00',
                    'messages' => [[
                        'author' => 'manager1@example.test',
                        'body' => 'Проверим электрощиток и уточним сроки устранения в течение рабочего дня.',
                        'created_at' => '2026-11-14 09:20:00',
                        'updated_at' => '2026-11-14 09:20:00',
                    ]],
                    'history' => [[
                        'user' => 'multi@example.test',
                        'from' => null,
                        'to' => RequestStatus::New,
                        'created_at' => '2026-11-14 09:15:00',
                    ]],
                ],
                [
                    'title' => 'Протечка трубы в подвале',
                    'description' => 'По стене в подвале идёт вода от трубы, влажность повышается и уже заметна мокрая плитка.',
                    'author' => 'resident1@example.test',
                    'status' => RequestStatus::InProgress,
                    'resolved_at' => null,
                    'created_at' => '2026-11-14 10:00:00',
                    'updated_at' => '2026-11-14 10:25:00',
                    'messages' => [[
                        'author' => 'manager1@example.test',
                        'body' => 'Согласовали ремонт и уже направили сантехника на объект.',
                        'created_at' => '2026-11-14 10:25:00',
                        'updated_at' => '2026-11-14 10:25:00',
                    ]],
                    'history' => [
                        [
                            'user' => 'resident1@example.test',
                            'from' => null,
                            'to' => RequestStatus::New,
                            'created_at' => '2026-11-14 10:00:00',
                        ],
                        [
                            'user' => 'manager1@example.test',
                            'from' => RequestStatus::New,
                            'to' => RequestStatus::InProgress,
                            'created_at' => '2026-11-14 10:20:00',
                        ],
                    ],
                ],
                [
                    'title' => 'Сломан домофон',
                    'description' => 'Домофон в подъезде перестал открывать дверь и не реагирует на звонки с улицы.',
                    'author' => 'resident1@example.test',
                    'status' => RequestStatus::Resolved,
                    'resolved_at' => '2026-11-14 12:35:00',
                    'created_at' => '2026-11-14 11:05:00',
                    'updated_at' => '2026-11-14 12:35:00',
                    'messages' => [[
                        'author' => 'manager1@example.test',
                        'body' => 'Домофон заменён, проверка безопасности уже проведена.',
                        'created_at' => '2026-11-14 12:35:00',
                        'updated_at' => '2026-11-14 12:35:00',
                    ]],
                    'history' => [
                        [
                            'user' => 'resident1@example.test',
                            'from' => null,
                            'to' => RequestStatus::New,
                            'created_at' => '2026-11-14 11:05:00',
                        ],
                        [
                            'user' => 'manager1@example.test',
                            'from' => RequestStatus::New,
                            'to' => RequestStatus::InProgress,
                            'created_at' => '2026-11-14 11:35:00',
                        ],
                        [
                            'user' => 'manager1@example.test',
                            'from' => RequestStatus::InProgress,
                            'to' => RequestStatus::Resolved,
                            'created_at' => '2026-11-14 12:35:00',
                        ],
                    ],
                ],
            ],
            'lesnoy' => [
                [
                    'title' => 'Не работает уличный фонарь',
                    'description' => 'Уличный свет в конце квартала не включается по вечерам, создаёт опасность для жителей.',
                    'author' => 'resident2@example.test',
                    'status' => RequestStatus::New,
                    'resolved_at' => null,
                    'created_at' => '2026-11-14 09:40:00',
                    'updated_at' => '2026-11-14 09:40:00',
                    'messages' => [[
                        'author' => 'manager2@example.test',
                        'body' => 'Организуем проверку сети и замену лампы в ближайшее время.',
                        'created_at' => '2026-11-14 09:50:00',
                        'updated_at' => '2026-11-14 09:50:00',
                    ]],
                    'history' => [[
                        'user' => 'resident2@example.test',
                        'from' => null,
                        'to' => RequestStatus::New,
                        'created_at' => '2026-11-14 09:40:00',
                    ]],
                ],
                [
                    'title' => 'Повреждено дорожное покрытие',
                    'description' => 'В проезде возле дома повреждён асфальт, видны глубокие выбоины и трещины.',
                    'author' => 'resident2@example.test',
                    'status' => RequestStatus::InProgress,
                    'resolved_at' => null,
                    'created_at' => '2026-11-14 10:45:00',
                    'updated_at' => '2026-11-14 11:00:00',
                    'messages' => [[
                        'author' => 'manager2@example.test',
                        'body' => 'Выполнили смету и согласовали дорожные работы с подрядчиком.',
                        'created_at' => '2026-11-14 11:00:00',
                        'updated_at' => '2026-11-14 11:00:00',
                    ]],
                    'history' => [
                        [
                            'user' => 'resident2@example.test',
                            'from' => null,
                            'to' => RequestStatus::New,
                            'created_at' => '2026-11-14 10:45:00',
                        ],
                        [
                            'user' => 'manager2@example.test',
                            'from' => RequestStatus::New,
                            'to' => RequestStatus::InProgress,
                            'created_at' => '2026-11-14 10:55:00',
                        ],
                    ],
                ],
                [
                    'title' => 'Не открывается калитка',
                    'description' => 'Ворота калитки перестали открываться с внутренней стороны и требуют срочного ремонта.',
                    'author' => 'resident2@example.test',
                    'status' => RequestStatus::Resolved,
                    'resolved_at' => '2026-11-14 13:10:00',
                    'created_at' => '2026-11-14 11:25:00',
                    'updated_at' => '2026-11-14 13:10:00',
                    'messages' => [[
                        'author' => 'manager2@example.test',
                        'body' => 'Калитка отремонтирована и снова работает в штатном режиме.',
                        'created_at' => '2026-11-14 13:10:00',
                        'updated_at' => '2026-11-14 13:10:00',
                    ]],
                    'history' => [
                        [
                            'user' => 'resident2@example.test',
                            'from' => null,
                            'to' => RequestStatus::New,
                            'created_at' => '2026-11-14 11:25:00',
                        ],
                        [
                            'user' => 'manager2@example.test',
                            'from' => RequestStatus::New,
                            'to' => RequestStatus::InProgress,
                            'created_at' => '2026-11-14 12:00:00',
                        ],
                        [
                            'user' => 'manager2@example.test',
                            'from' => RequestStatus::InProgress,
                            'to' => RequestStatus::Resolved,
                            'created_at' => '2026-11-14 13:10:00',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach ($this->definitions() as $slug => $requests) {
            $community = Community::query()->where('slug', $slug)->firstOrFail();

            foreach ($requests as $requestData) {
                $request = ServiceRequest::query()
                    ->where('community_id', $community->id)
                    ->where('title', $requestData['title'])
                    ->first();

                if ($request === null) {
                    $author = User::query()
                        ->where('email', $requestData['author'])
                        ->firstOrFail();
                    $request = new ServiceRequest([
                        'community_id' => $community->id,
                        'user_id' => $author->id,
                        'title' => $requestData['title'],
                        'description' => $requestData['description'],
                        'status' => $requestData['status'],
                        'resolved_at' => $requestData['resolved_at'],
                    ]);
                    $request->forceFill([
                        'created_at' => CarbonImmutable::parse($requestData['created_at']),
                        'updated_at' => CarbonImmutable::parse($requestData['updated_at']),
                    ])->save();
                }

                foreach ($requestData['messages'] as $messageData) {
                    $this->ensureMessage($request, $messageData);
                }

                foreach ($requestData['history'] as $historyData) {
                    $this->ensureHistory($request, $historyData);
                }
            }
        }
    }

    /**
     * @param  array{author: string, body: string, created_at: string, updated_at: string}  $messageData
     */
    private function ensureMessage(
        ServiceRequest $request,
        array $messageData,
    ): void {
        $user = User::query()
            ->where('email', $messageData['author'])
            ->firstOrFail();

        $message = $request->messages()
            ->where('user_id', $user->id)
            ->where('body', $messageData['body'])
            ->first();
        if ($message !== null) {
            return;
        }

        $message = new RequestMessage([
            'service_request_id' => $request->id,
            'user_id' => $user->id,
            'body' => $messageData['body'],
        ]);
        $message->save();
        $message->forceFill([
            'created_at' => CarbonImmutable::parse($messageData['created_at']),
            'updated_at' => CarbonImmutable::parse($messageData['updated_at']),
        ])->saveQuietly();

        $request->forceFill([
            'updated_at' => CarbonImmutable::parse($messageData['updated_at']),
        ])->saveQuietly();
    }

    /**
     * @param  array{user: ?string, from: ?RequestStatus, to: RequestStatus, created_at: string}  $historyData
     */
    private function ensureHistory(ServiceRequest $request, array $historyData): void
    {
        $query = $request->statusHistories()
            ->where('to_status', $historyData['to']->value);

        if ($historyData['from'] === null) {
            $query->whereNull('from_status');
        } else {
            $query->where('from_status', $historyData['from']->value);
        }

        if ($historyData['user'] !== null) {
            $user = User::query()
                ->where('email', $historyData['user'])
                ->firstOrFail();
            $query->where('user_id', $user->id);
        } else {
            $query->whereNull('user_id');
        }

        $history = $query->first();
        if ($history !== null) {
            return;
        }

        $history = new RequestStatusHistory([
            'service_request_id' => $request->id,
            'user_id' => $historyData['user'] === null
                ? null
                : User::query()->where('email', $historyData['user'])->firstOrFail()->id,
            'from_status' => $historyData['from']?->value,
            'to_status' => $historyData['to']->value,
        ]);
        $history->save();
        $history->forceFill([
            'created_at' => CarbonImmutable::parse($historyData['created_at']),
        ])->saveQuietly();

        $request->forceFill([
            'updated_at' => CarbonImmutable::parse($historyData['created_at']),
        ])->saveQuietly();
    }
}
