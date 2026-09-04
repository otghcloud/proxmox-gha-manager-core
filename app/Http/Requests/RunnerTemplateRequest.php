<?php

namespace App\Http\Requests;

use App\Enums\PoolOs;
use App\Models\Credential;
use App\Services\Builds\TemplateCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RunnerTemplateRequest extends FormRequest
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
        $template = $this->route('runnerTemplate');

        return [
            'environment_id' => ['required', 'exists:environments,id'],
            'target_ids' => ['array'],
            'target_ids.*' => ['integer', 'exists:proxmox_targets,id', 'distinct'],
            'mappings' => ['array'],
            'mappings.*.build_iso_file' => ['nullable', 'string', 'max:255'],
            'mappings.*.build_iso_url' => ['nullable', 'url', 'max:2000'],
            'mappings.*.build_cores' => ['nullable', 'integer', 'min:1', 'max:512'],
            'mappings.*.build_memory_mb' => ['nullable', 'integer', 'min:1024'],
            'mappings.*.build_disk_gb' => ['nullable', 'integer', 'min:20'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('runner_templates', 'name')
                    ->where('environment_id', $this->input('environment_id'))
                    ->ignore($template),
            ],
            'os' => ['required', Rule::enum(PoolOs::class)],
            'credential_id' => ['required', 'integer', 'exists:credentials,id'],
            'description' => ['nullable', 'string', 'max:2000'],

            'template_catalog_id' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $entry = app(TemplateCatalog::class)->entryForId($this->input('template_catalog_id'));

        $credentialId = $this->input('credential_id');
        if ($credentialId === null && ($entry?->platform() === PoolOs::Linux->value || $this->input('os') === PoolOs::Linux->value)) {
            $credentialId = Credential::query()->where('name', 'Default Linux SSH')->value('id');
        }

        $this->merge([
            'name' => $entry?->name(),
            'os' => $entry?->platform(),
            'credential_id' => $credentialId,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $entry = app(TemplateCatalog::class)->entryForId($this->input('template_catalog_id'));

            if ($entry === null) {
                $validator->errors()->add('template_catalog_id', 'The selected template is not present in the installed catalog.');
            } elseif (! $entry->isBuildable()) {
                $validator->errors()->add('template_catalog_id', $entry->disabledReason() ?? 'The selected template is not buildable.');
            }

            $credential = Credential::find($this->input('credential_id'));
            if ($credential === null || $credential->os !== PoolOs::tryFrom((string) $this->input('os'))) {
                $validator->errors()->add('credential_id', 'Select a credential for the template operating system.');
            }

            $selected = array_map('intval', $this->input('target_ids', []));

            foreach (array_keys($this->input('mappings', [])) as $targetId) {
                if (! in_array((int) $targetId, $selected, true)) {
                    $validator->errors()->add("mappings.{$targetId}", 'This node is not selected for the template.');
                }
            }
        });
    }
}
