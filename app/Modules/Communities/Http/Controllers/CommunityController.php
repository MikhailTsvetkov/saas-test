<?php

declare(strict_types=1);

namespace App\Modules\Communities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communities\Actions\ListUserCommunities;
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
            'communities' => fn (): array => $communities->handle($request->user()),
        ]);
    }
}
