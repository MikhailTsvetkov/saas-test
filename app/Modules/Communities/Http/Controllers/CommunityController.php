<?php

declare(strict_types=1);

namespace App\Modules\Communities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communities\Actions\ListUserCommunities;
use App\Modules\Communities\Data\CommunityListItemData;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

final class CommunityController extends Controller
{
    public function index(
        Request $request,
        ListUserCommunities $communities,
        ResponseFactory $inertia,
    ): Response {
        return $inertia->render('Communities/Index', [
            'communities' => fn (): array => array_map(
                fn (CommunityListItemData $community): array => $community->toArray(),
                $communities->handle($request->user()),
            ),
        ]);
    }
}
