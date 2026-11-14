<?php

declare(strict_types=1);

namespace App\Modules\Requests\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communities\Models\Community;
use App\Modules\Requests\Actions\AddRequestMessage;
use App\Modules\Requests\Actions\FindCommunityRequest;
use App\Modules\Requests\Http\Requests\StoreRequestMessageRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\ResponseFactory;

final class RequestMessageController extends Controller
{
    public function store(
        StoreRequestMessageRequest $request,
        Community $community,
        string $requestId,
        AddRequestMessage $addRequestMessage,
        FindCommunityRequest $findCommunityRequest,
        ResponseFactory $inertia,
    ): RedirectResponse {
        $serviceRequest = $findCommunityRequest->handle($requestId);
        $addRequestMessage->handle(
            $request->user(),
            $serviceRequest,
            trim($request->validated('body')),
        );

        $inertia->flash('success', 'Комментарий добавлен.');

        return to_route('communities.requests.show', [
            'community' => $community->slug,
            'request' => $serviceRequest->id,
        ]);
    }
}
