<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GitHubAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $account = $this->route('github_account');
        $secret = $account === null ? 'required' : 'nullable';

        return [
            'account_type' => ['required', Rule::in(['organization', 'user'])],
            'login' => ['required', 'string', 'max:255'],
            'webhook_id' => [
                $account === null ? 'nullable' : 'required',
                'uuid',
                Rule::unique('github_accounts', 'webhook_id')->ignore($account),
            ],
            'github_token' => [$secret, 'string'],
            'github_webhook_secret' => [$secret, 'string'],
            'github_api_url' => ['required', 'url', 'max:255'],
            'github_runner_group_id' => ['required', 'integer', 'min:1'],
            'github_work_folder' => ['required', 'string', 'max:255'],
        ];
    }
}
