<?php

declare(strict_types=1);

namespace App\Modules\Requests\Listeners;

use App\Modules\Communities\Enums\CommunityRole;
use App\Modules\Communities\Models\CommunityMember;
use App\Modules\Requests\Events\RequestCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

final class SendRequestCreatedNotification implements ShouldQueue
{
    public function handle(RequestCreated $event): void
    {
        if (! app()->environment('local', 'development')) {
            return;
        }

        $managers = CommunityMember::query()
            ->with('user:id,name,email')
            ->where('community_id', $event->serviceRequest->community_id)
            ->where('role', CommunityRole::Manager)
            ->get();

        foreach ($managers as $manager) {
            Log::info('Уведомление управляющему о новом обращении.', [
                'community_id' => $event->serviceRequest->community_id,
                'manager_id' => $manager->user_id,
                'manager_email' => $manager->user->email,
                'request_id' => $event->serviceRequest->id,
                'request_title' => $event->serviceRequest->title,
            ]);
        }
    }
}
