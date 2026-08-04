<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AcceptRefinementRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'instruction' => ['required', 'string', 'min:2', 'max:500'],
            'before' => ['required', 'string', 'max:1200'],
            'after' => ['required', 'string', 'max:1200'],
        ];
    }
}
