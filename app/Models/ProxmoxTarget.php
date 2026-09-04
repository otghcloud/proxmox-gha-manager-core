<?php

namespace App\Models;

use App\Models\Concerns\HasBreadcrumbLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProxmoxTarget extends Model
{
    use HasBreadcrumbLabel;
    use HasFactory;

    protected $guarded = ['id'];

    /** Standard login ('password', ticket auth) or API token ('api_token', the default). */
    public const AUTH_REALM_API_TOKEN = 'api_token';

    public const AUTH_REALM_PASSWORD = 'password';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'proxmox_verify_tls' => 'boolean',
            'proxmox_token_secret' => 'encrypted',
            'proxmox_password' => 'encrypted',
            'current_vm_count' => 'integer',
            'max_total_vms' => 'integer',
            'last_health_check_at' => 'datetime',
            'drained_at' => 'datetime',
            'template_vmid_range_start' => 'integer',
            'template_vmid_range_end' => 'integer',
            'runner_vmid_range_start' => 'integer',
            'runner_vmid_range_end' => 'integer',
            'vlan_tag' => 'integer',
        ];
    }

    public function usesPasswordAuth(): bool
    {
        return $this->proxmox_auth_realm === self::AUTH_REALM_PASSWORD;
    }

    /**
     * The `netN` value Proxmox expects for a VM on this node's network.
     */
    public function networkAdapter(string $model = 'virtio'): string
    {
        $adapter = $model.',bridge='.($this->network_bridge ?: 'vmbr0');

        return $this->vlan_tag === null ? $adapter : $adapter.',tag='.$this->vlan_tag;
    }

    public function runnerTemplates(): BelongsToMany
    {
        return $this->belongsToMany(RunnerTemplate::class, 'runner_template_target', 'proxmox_target_id', 'runner_template_id')
            ->using(RunnerTemplateTarget::class)
            ->withPivot(['template_vmid', 'generation', 'build_iso_file', 'build_iso_url', 'build_cores', 'build_memory_mb', 'build_disk_gb', 'availability_status', 'last_built_at']);
    }

    public function pools(): BelongsToMany
    {
        return $this->belongsToMany(Pool::class, 'pool_proxmox_target')
            ->withPivot(['preference', 'min_idle_runners', 'max_concurrent'])
            ->withTimestamps();
    }

    public function runners(): HasMany
    {
        return $this->hasMany(Runner::class, 'proxmox_target_id');
    }
}
