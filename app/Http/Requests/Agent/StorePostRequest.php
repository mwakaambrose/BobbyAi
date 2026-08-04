<?php

namespace App\Http\Requests\Agent;

use App\Enums\AccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    /**
     * Fall back to the team's connected account.
     *
     * The Home composer asks for a brief and nothing else, which is the whole
     * point of it. Most teams have exactly one account, so choosing it here
     * keeps the entry point to a single field without making the account
     * optional for callers that do name one.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('social_account_id')) {
            return;
        }

        $account = $this->user()->currentTeam
            ->socialAccounts()
            ->where('status', AccountStatus::Connected)
            ->first();

        if ($account !== null) {
            $this->merge(['social_account_id' => $account->id]);
        }
    }

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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'social_account_id.required' => __('Connect a LinkedIn account before drafting a post.'),
        ];
    }
}
