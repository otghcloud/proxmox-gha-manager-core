<?php

namespace App\Http\Requests;

use App\Enums\PoolOs;
use App\Models\Credential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $credential = $this->route('credential');
        $secret = $credential === null ? 'nullable' : 'nullable';

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('credentials')->where('os', $this->input('os'))->ignore($credential)],
            'os' => ['required', Rule::enum(PoolOs::class)],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => [$secret, 'string'],
            'private_key' => [$secret, 'string'],
            'public_key' => [$secret, 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $credential = $this->route('credential');
            $hasExisting = $credential instanceof Credential && $credential->hasAuthenticationMaterial();

            if (blank($this->input('password')) && (blank($this->input('private_key')) || blank($this->input('public_key'))) && ! $hasExisting) {
                $validator->errors()->add('password', 'Provide a password or both an SSH private and public key.');
            }
        });
    }
}
