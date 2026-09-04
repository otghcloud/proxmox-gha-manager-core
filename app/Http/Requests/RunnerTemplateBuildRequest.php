<?php

namespace App\Http\Requests;

use App\Services\Builds\TemplateCatalog;
use App\Services\Builds\TemplateRebuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RunnerTemplateBuildRequest extends FormRequest
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
        return [
            'mode' => ['nullable', Rule::in([TemplateRebuilder::MODE_SEQUENTIAL, TemplateRebuilder::MODE_PARALLEL])],
            'builder' => ['nullable', 'string', 'max:64'],
            'target_ids' => ['array'],
            'target_ids.*' => ['integer', 'exists:proxmox_targets,id'],
        ];
    }

    public function mode(): string
    {
        return $this->input('mode') ?: TemplateRebuilder::MODE_SEQUENTIAL;
    }

    public function builder(): ?string
    {
        $builder = $this->input('builder');

        return is_string($builder) && $builder !== '' ? $builder : null;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->builder() === null) {
                return;
            }

            $template = $this->route('runnerTemplate');
            $catalogId = $this->input('template_catalog_id') ?: $template?->template_catalog_id;
            $entry = app(TemplateCatalog::class)->entryForId($catalogId, $this->builder());

            if ($entry === null) {
                $validator->errors()->add('builder', 'The selected build method is not available for this template.');
            } elseif (! $entry->isBuildable()) {
                $validator->errors()->add('builder', $entry->disabledReason() ?? 'The selected build method is not buildable.');
            }
        });
    }

    /**
     * @return array<int, int>
     */
    public function targetIds(): array
    {
        return array_map('intval', $this->input('target_ids', []));
    }
}
