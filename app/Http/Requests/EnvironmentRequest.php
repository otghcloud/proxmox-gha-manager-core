<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnvironmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $environment = $this->route('environment');
        $isUpdate = $environment !== null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('environments', 'slug')->ignore($environment),
            ],
            'github_account_id' => ['required', 'exists:github_accounts,id'],
            'enabled' => ['boolean'],

            'max_lifetime_seconds' => ['required', 'integer', 'min:60'],
            'idle_timeout_seconds' => ['required', 'integer', 'min:60'],
            'job_claim_timeout_seconds' => ['required', 'integer', 'min:5'],
            'keep_failed_vms' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => $this->boolean('enabled'),
            'proxmox_verify_tls' => $this->boolean('proxmox_verify_tls'),
            'keep_failed_vms' => $this->boolean('keep_failed_vms'),
            'slug' => $this->filled('slug') ? $this->string('slug')->slug()->toString() : $this->string('name')->slug()->toString(),
        ]);
    }
}
