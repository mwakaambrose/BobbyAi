<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApprovePostRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:3000'],
        ];
    }

    /**
     * Ensure the post's account has a verified browser session.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $account = $this->route('post')->account;

                if (! $account || ! $account->status->isPublishable()) {
                    $validator->errors()->add(
                        'social_account_id',
                        __('Connect the LinkedIn account before publishing.'),
                    );
                }
            },
        ];
    }
}
