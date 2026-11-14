<?php

declare(strict_types=1);

namespace App\Modules\Requests\Enums;

enum RequestStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';

    /** @return list<self> */
    public function allowedNextStatuses(): array
    {
        return match ($this) {
            self::New => [self::InProgress, self::Resolved],
            self::InProgress => [self::Resolved],
            self::Resolved => [self::InProgress],
        };
    }
}
