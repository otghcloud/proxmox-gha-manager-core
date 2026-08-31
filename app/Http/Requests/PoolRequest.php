<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PoolRequest extends FormRequest
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
        $pool = $this->route('pool');

        return [
            'environment_id' => ['required', 'exists:environments,id'],
            'runner_template_id' => ['required', 'exists:runner_templates,id'],
            'name' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('pools', 'name')
                    ->where('environment_id', $this->input('environment_id'))
                    ->ignore($pool),
            ],
            'enabled' => ['boolean'],
            'labels' => ['required', 'array', 'min:1'],
            'labels.*' => ['required', 'string', 'max:255'],
            'cores' => ['required', 'integer', 'min:1', 'max:512'],
            'memory' => ['required', 'integer', 'min:512'],
            'boot_timeout_seconds' => ['required', 'integer', 'min:30'],
            'runner_dir' => ['nullable', 'string', 'max:255'],
            'nodes' => ['array'],
            'nodes.*.enabled' => ['boolean'],
            'nodes.*.min_idle_runners' => ['exclude_unless:nodes.*.enabled,true', 'required', 'integer', 'min:0', 'lte:nodes.*.max_concurrent'],
            'nodes.*.max_concurrent' => ['exclude_unless:nodes.*.enabled,true', 'required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && $this->nodeLimits() === []) {
                    $validator->errors()->add('nodes', 'Select at least one node for this pool and give it a warm pool and concurrency limit.');
                }
            },
        ];
    }

    /**
     * Per-node limits keyed by target id, ready for a pivot sync.
     *
     * @return array<int, array<string, int>>
     */
    public function nodeLimits(): array
    {
        return collect($this->validated('nodes') ?? [])
            ->filter(fn (array $values): bool => ($values['enabled'] ?? false) === true)
            ->map(fn (array $values): array => [
                'min_idle_runners' => (int) $values['min_idle_runners'],
                'max_concurrent' => (int) $values['max_concurrent'],
            ])
            ->all();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => $this->boolean('enabled'),
            'labels' => $this->normaliseLabels(),
            'nodes' => $this->normaliseNodes(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normaliseNodes(): array
    {
        $nodes = $this->input('nodes');

        if (! is_array($nodes)) {
            return [];
        }

        return collect($nodes)
            ->map(fn ($values): array => [
                'enabled' => filter_var($values['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'min_idle_runners' => $values['min_idle_runners'] ?? null,
                'max_concurrent' => $values['max_concurrent'] ?? null,
            ])
            ->all();
    }

    /**
     * The label editor submits a comma separated string; store it as a clean unique list.
     *
     * @return array<int, string>
     */
    private function normaliseLabels(): array
    {
        $labels = $this->input('labels');

        if (is_string($labels)) {
            $labels = explode(',', $labels);
        }

        if (! is_array($labels)) {
            return [];
        }

        return collect($labels)
            ->map(fn ($label): string => trim((string) $label))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
