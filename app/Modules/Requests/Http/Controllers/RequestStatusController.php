<?php

declare(strict_types=1);

namespace App\Modules\Requests\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communities\Models\Community;
use App\Modules\Requests\Actions\ChangeRequestStatus;
use App\Modules\Requests\Actions\FindCommunityRequest;
use App\Modules\Requests\Http\Requests\UpdateRequestStatusRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\ResponseFactory;

final class RequestStatusController extends Controller
{
    public function update(
        UpdateRequestStatusRequest $request,
        Community $community,
        string $requestId,
        ChangeRequestStatus $changeRequestStatus,
        FindCommunityRequest $findCommunityRequest,
        ResponseFactory $inertia,
    ): RedirectResponse {
        $serviceRequest = $findCommunityRequest->handle($requestId);
        $changeRequestStatus->handle(
            $request->user(),
            $serviceRequest,
            $request->validated('status'),
        );

        $inertia->flash('success', 'Статус обращения обновлён.');

        return to_route('communities.requests.show', [
            'community' => $community->slug,
            'request' => $serviceRequest->id,
        ]);
    }
}
