<?php

namespace App\Http\Requests;

use App\Enums\BuildTarget;
use App\Enums\PoolOs;
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
            'description' => ['nullable', 'string', 'max:2000'],

            'build_target' => ['required', Rule::enum(BuildTarget::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $buildTarget = BuildTarget::tryFrom((string) $this->input('build_target'));

        $this->merge([
            'build_target' => $this->filled('build_target') ? $this->input('build_target') : null,
            'name' => $buildTarget?->value,
            'os' => $buildTarget?->os()->value,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $selected = array_map('intval', $this->input('target_ids', []));

            foreach (array_keys($this->input('mappings', [])) as $targetId) {
                if (! in_array((int) $targetId, $selected, true)) {
                    $validator->errors()->add("mappings.{$targetId}", 'This node is not selected for the template.');
                }
            }
        });
    }
}
