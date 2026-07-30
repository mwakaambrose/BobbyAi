<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandProfileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'voice' => ['nullable', 'string', 'max:1000'],
            'audience' => ['nullable', 'string', 'max:1000'],
            'topics' => ['nullable', 'array', 'max:20'],
            'topics.*' => ['string', 'max:60'],
            'banned_terms' => ['nullable', 'array', 'max:50'],
            'banned_terms.*' => ['string', 'max:60'],
            'examples' => ['nullable', 'array', 'max:10'],
            'examples.*' => ['string', 'max:3000'],
        ];
    }
}
