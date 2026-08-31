<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProxmoxTargetRequest extends FormRequest
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
        $target = $this->route('target');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('proxmox_targets', 'slug')->ignore($target),
            ],
            'proxmox_url' => ['required', 'url', 'max:255'],
            'proxmox_node' => ['required', 'string', 'max:255'],
            'proxmox_token_id' => ['required', 'string', 'max:255'],
            'proxmox_token_secret' => [$target === null ? 'required' : 'nullable', 'string'],
            'proxmox_verify_tls' => ['boolean'],
            'proxmox_ca_bundle' => ['nullable', 'string', 'max:255'],
            'proxmox_resource_pool' => ['nullable', 'string', 'max:255'],
            'enabled' => ['boolean'],
            'drained' => ['boolean'],
            'max_total_vms' => ['required', 'integer', 'min:1'],
            'template_vmid_range_start' => ['required', 'integer', 'min:100'],
            'template_vmid_range_end' => ['required', 'integer', 'gt:template_vmid_range_start'],
            'runner_vmid_range_start' => ['required', 'integer', 'min:100'],
            'runner_vmid_range_end' => ['required', 'integer', 'gt:runner_vmid_range_start'],
            'build_iso_storage' => ['nullable', 'string', 'max:255'],
            'build_vm_storage' => ['nullable', 'string', 'max:255'],
            'build_cpu_type' => ['nullable', 'string', 'max:255'],
            'network_bridge' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z][A-Za-z0-9_.-]*$/'],
            'vlan_tag' => ['nullable', 'integer', 'min:1', 'max:4094'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => $this->boolean('enabled'),
            'proxmox_verify_tls' => $this->boolean('proxmox_verify_tls'),
            'network_bridge' => $this->filled('network_bridge') ? trim((string) $this->input('network_bridge')) : 'vmbr0',
            'vlan_tag' => $this->filled('vlan_tag') ? $this->input('vlan_tag') : null,
            'slug' => $this->filled('slug')
                ? $this->string('slug')->slug()->toString()
                : $this->string('name')->slug()->toString(),
        ]);
    }
}
