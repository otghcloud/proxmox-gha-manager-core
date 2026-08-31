<?php

namespace App\Http\Requests;

use App\Services\Builds\TemplateRebuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'target_ids' => ['array'],
            'target_ids.*' => ['integer', 'exists:proxmox_targets,id'],
        ];
    }

    public function mode(): string
    {
        return $this->input('mode') ?: TemplateRebuilder::MODE_SEQUENTIAL;
    }

    /**
     * @return array<int, int>
     */
    public function targetIds(): array
    {
        return array_map('intval', $this->input('target_ids', []));
    }
}
