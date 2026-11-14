<?php

declare(strict_types=1);

namespace App\Modules\Requests\Http\Requests;

use App\Modules\Requests\Actions\FindCommunityRequest;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Http\FormRequest;

final class StoreRequestMessageRequest extends FormRequest
{
    public function __construct(private readonly FindCommunityRequest $findCommunityRequest)
    {
        parent::__construct();
    }

    public function authorize(Gate $gate): bool
    {
        $serviceRequest = $this->findCommunityRequest->handle(
            (string) $this->route('request'),
        );
        $gate->authorize('addMessage', $serviceRequest);

        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:3000', 'not_regex:/^\s+$/u'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'Введите текст комментария.',
            'body.string' => 'Комментарий должен быть строкой.',
            'body.min' => 'Комментарий не должен быть пустым.',
            'body.max' => 'Комментарий не должен превышать 3000 символов.',
            'body.not_regex' => 'Комментарий не должен состоять только из пробелов.',
        ];
    }
}
