<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StoreRefinementRequest extends FormRequest
{
    /**
     * The buffer arrives as three segments rather than a body and two offsets:
     * the browser counts UTF-16 code units and PHP counts codepoints, so any
     * post containing an emoji would slice in the wrong place.
     *
     * Selecting the first or last words of a draft leaves one side empty, and
     * an empty string reaches validation as null, so both sides are nullable.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'before' => ['present', 'nullable', 'string'],
            'selection' => ['required', 'string', 'min:1', 'max:1200'],
            'after' => ['present', 'nullable', 'string'],
            'instruction' => ['required', 'string', 'min:2', 'max:500'],
        ];
    }

    /**
     * Get one side of the buffer as text, since an empty side arrives as null.
     *
     * Not named `segment`: that is already a request method for URL segments.
     */
    public function side(string $key): string
    {
        return (string) $this->validated($key);
    }
}
