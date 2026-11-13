<?php

declare(strict_types=1);

namespace App\Modules\Communities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communities\Models\Community;
use Inertia\Response;
use Inertia\ResponseFactory;

final class CommunityDashboardController extends Controller
{
    public function show(Community $community, ResponseFactory $inertia): Response
    {
        return $inertia->render('Communities/Dashboard', [
            'description' => $community->description,
        ]);
    }
}
