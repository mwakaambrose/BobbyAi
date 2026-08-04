<?php

namespace App\Http\Requests\Agent;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostingScheduleRequest extends FormRequest
{
    /** @var array<int, string> */
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'days' => ['required_if:is_active,true', 'array'],
            'days.*' => [Rule::in(self::DAYS)],
            'time_of_day' => ['required', 'date_format:H:i'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'posts_per_run' => ['required', 'integer', 'min:1', 'max:5'],
            'social_account_id' => [
                'required_if:is_active,true',
                'nullable',
                Rule::exists('social_accounts', 'id')->where('team_id', $this->user()->currentTeam->id),
            ],
            'notify_emails' => ['array', 'max:10'],
            'notify_emails.*' => ['email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'days.required_if' => __('Choose at least one day for Bobby to write on.'),
            'social_account_id.required_if' => __('Choose the account Bobby should write for.'),
        ];
    }
}
