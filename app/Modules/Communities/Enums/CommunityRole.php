<?php

declare(strict_types=1);

namespace App\Modules\Communities\Enums;

enum CommunityRole: string
{
    case Resident = 'resident';
    case Manager = 'manager';
}
