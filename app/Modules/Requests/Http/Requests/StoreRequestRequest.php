<?php

declare(strict_types=1);

namespace App\Modules\Requests\Http\Requests;

use App\Modules\Requests\Models\ServiceRequest;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Http\FormRequest;

final class StoreRequestRequest extends FormRequest
{
    public function authorize(Gate $gate): bool
    {
        $gate->authorize('create', ServiceRequest::class);

        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Введите заголовок.',
            'title.string' => 'Заголовок должен быть строкой.',
            'title.min' => 'Заголовок должен содержать не менее 5 символов.',
            'title.max' => 'Заголовок не должен превышать 255 символов.',
            'description.required' => 'Введите описание.',
            'description.string' => 'Описание должно быть строкой.',
            'description.min' => 'Описание должно содержать не менее 10 символов.',
            'description.max' => 'Описание не должно превышать 5000 символов.',
        ];
    }
}
