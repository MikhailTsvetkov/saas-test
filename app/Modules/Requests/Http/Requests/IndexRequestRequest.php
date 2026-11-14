<?php

declare(strict_types=1);

namespace App\Modules\Requests\Http\Requests;

use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class IndexRequestRequest extends FormRequest
{
    public function authorize(Gate $gate): bool
    {
        $gate->authorize('viewAny', ServiceRequest::class);

        return true;
    }

    /** @return array<string, list<string|Enum>> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(RequestStatus::class)],
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.enum' => 'Выберите существующий статус.',
            'page.integer' => 'Номер страницы должен быть целым числом.',
            'page.min' => 'Номер страницы должен быть не меньше 1.',
            'page.max' => 'Номер страницы слишком большой.',
        ];
    }
}
