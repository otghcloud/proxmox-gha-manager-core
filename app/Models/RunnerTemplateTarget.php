<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RunnerTemplateTarget extends Pivot
{
    protected $table = 'runner_template_target';

    public $incrementing = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'template_vmid' => 'integer',
            'generation' => 'integer',
            'build_cores' => 'integer',
            'build_memory_mb' => 'integer',
            'build_disk_gb' => 'integer',
            'last_built_at' => 'datetime',
        ];
    }

    public function runnerTemplate(): BelongsTo
    {
        return $this->belongsTo(RunnerTemplate::class);
    }

    public function proxmoxTarget(): BelongsTo
    {
        return $this->belongsTo(ProxmoxTarget::class);
    }
}
