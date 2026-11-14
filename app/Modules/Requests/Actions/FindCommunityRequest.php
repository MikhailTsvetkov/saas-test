<?php

declare(strict_types=1);

namespace App\Modules\Requests\Actions;

use App\Modules\Communities\CurrentCommunity;
use App\Modules\Requests\Models\ServiceRequest;

final readonly class FindCommunityRequest
{
    public function __construct(private CurrentCommunity $currentCommunity) {}

    public function handle(string $id): ServiceRequest
    {
        abort_unless(filter_var(
            $id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        ) !== false, 404);

        return ServiceRequest::query()
            ->where('community_id', $this->currentCommunity->community()->id)
            ->findOrFail($id);
    }
}
