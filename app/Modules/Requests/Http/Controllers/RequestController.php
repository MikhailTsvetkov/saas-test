<?php

declare(strict_types=1);

namespace App\Modules\Requests\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communities\Models\Community;
use App\Modules\Requests\Actions\CreateRequest;
use App\Modules\Requests\Actions\ListRequests;
use App\Modules\Requests\Actions\ShowRequest;
use App\Modules\Requests\Data\ServiceRequestListItemData;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Http\Requests\IndexRequestRequest;
use App\Modules\Requests\Http\Requests\StoreRequestRequest;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

final class RequestController extends Controller
{
    public function index(
        IndexRequestRequest $request,
        Community $community,
        ListRequests $listRequests,
        Gate $gate,
        UrlGenerator $urls,
        ResponseFactory $inertia,
    ): Response {
        $status = $request->validated('status');
        $canCreate = $gate->allows('create', ServiceRequest::class);

        return $inertia->render('Requests/Index', [
            'requests' => fn (): array => $listRequests->handle(
                $request->user(),
                $status === null ? null : RequestStatus::from($status),
                (int) $request->validated('page', 1),
            )->through(fn (ServiceRequestListItemData $item): array => $item->toArray())->toArray(),
            'filters' => $inertia->always(['status' => $status]),
            'createUrl' => $inertia->always($canCreate
                ? $urls->route(
                    'communities.requests.create',
                    ['community' => $community->slug],
                )
                :
                null),
        ]);
    }

    public function create(
        Community $community,
        Gate $gate,
        UrlGenerator $urls,
        ResponseFactory $inertia,
    ): Response {
        $gate->authorize('create', ServiceRequest::class);

        return $inertia->render('Requests/Create', [
            'storeUrl' => $inertia->always($urls->route(
                'communities.requests.store',
                ['community' => $community->slug],
            )),
        ]);
    }

    public function store(
        StoreRequestRequest $request,
        Community $community,
        CreateRequest $createRequest,
        ResponseFactory $inertia,
    ): RedirectResponse {
        $serviceRequest = $createRequest->handle(
            $request->user(),
            $request->validated('title'),
            $request->validated('description'),
        );
        $inertia->flash('success', 'Обращение создано.');

        return to_route('communities.requests.show', [
            'community' => $community->slug,
            'request' => $serviceRequest->id,
        ]);
    }

    public function show(
        Request $httpRequest,
        Community $community,
        string $request,
        ShowRequest $showRequest,
        ResponseFactory $inertia,
    ): Response {
        $serviceRequest = $showRequest->handle($httpRequest->user(), $request);

        return $inertia->render('Requests/Show', [
            'serviceRequest' => fn (): array => $serviceRequest->toArray(),
        ]);
    }
}
