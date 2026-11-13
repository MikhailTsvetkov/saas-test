<?php

declare(strict_types=1);

namespace App\Modules\Communities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communities\Actions\ListCommunityMembers;
use App\Modules\Communities\Models\Community;
use Inertia\Response;
use Inertia\ResponseFactory;

final class CommunityMemberController extends Controller
{
    public function index(
        Community $community,
        ListCommunityMembers $members,
        ResponseFactory $inertia,
    ): Response {
        return $inertia->render('Communities/Members', [
            'members' => fn (): array => $members->handle(),
        ]);
    }
}
