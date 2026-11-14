<?php

declare(strict_types=1);

namespace App\Modules\Requests\Http\Requests;

use App\Modules\Requests\Actions\FindCommunityRequest;
use App\Modules\Requests\Enums\RequestStatus;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRequestStatusRequest extends FormRequest
{
    public function authorize(Gate $gate): bool
    {
        $serviceRequest = app(FindCommunityRequest::class)
            ->handle((string) $this->route('request'));
        $gate->authorize('changeStatus', $serviceRequest);

        return true;
    }

    /** @return array<string, list<string|array{0: string, 1: string}>> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_map(
                static fn (RequestStatus $status): string => $status->value,
                RequestStatus::cases(),
            ))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'Укажите статус обращения.',
            'status.string' => 'Статус должен быть строкой.',
            'status.in' => 'Неверный статус обращения.',
        ];
    }
}
