<?php

declare(strict_types=1);

namespace App\Modules\Requests\Enums;

enum RequestStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
}
