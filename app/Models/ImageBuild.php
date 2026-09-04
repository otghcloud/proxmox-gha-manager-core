<?php

namespace App\Models;

use App\Enums\BuildStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ImageBuild extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => BuildStatus::class,
            'exit_code' => 'integer',
            'process_pid' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'proxmox_target_id' => 'integer',
            'template_vmid' => 'integer',
            'sequence' => 'integer',
            'template_catalog_id' => 'string',
            'builder_type' => 'string',
            'credential_id' => 'integer',
        ];
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function runnerTemplate(): BelongsTo
    {
        return $this->belongsTo(RunnerTemplate::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function proxmoxTarget(): BelongsTo
    {
        return $this->belongsTo(ProxmoxTarget::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function credentialSnapshot(): HasOne
    {
        return $this->hasOne(BuildCredential::class);
    }

    public function logEntries(): MorphMany
    {
        return $this->morphMany(LogEntry::class, 'loggable');
    }

    public function getBreadcrumbLabel(): string
    {
        return (string) ($this->template_catalog_id ?: 'Build '.$this->getKey());
    }

    public function storedLog(): ?LogEntry
    {
        return $this->logEntries()->where('channel', LogEntry::CHANNEL_BUILD)->first();
    }
}
