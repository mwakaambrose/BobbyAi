<?php

namespace App\Http\Requests\Agent;

use App\Enums\IdeaKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostIdeaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['sometimes', 'string', 'min:4', 'max:500'],
            'kind' => ['sometimes', 'nullable', Rule::enum(IdeaKind::class)],
        ];
    }
}
