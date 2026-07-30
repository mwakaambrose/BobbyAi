<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brief' => ['required', 'string', 'min:8', 'max:2000'],
            'social_account_id' => [
                'required',
                Rule::exists('social_accounts', 'id')->where('team_id', $this->user()->currentTeam->id),
            ],
        ];
    }
}
