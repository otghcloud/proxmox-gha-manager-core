<?php

namespace App\Models;

use App\Models\Concerns\HasBreadcrumbLabel;
use App\Services\SettingsRepository;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Environment extends Model
{
    use HasBreadcrumbLabel;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'keep_failed_vms' => 'boolean',
            'max_lifetime_seconds' => 'integer',
            'idle_timeout_seconds' => 'integer',
            'job_claim_timeout_seconds' => 'integer',
        ];
    }

    public function runnerTemplates(): HasMany
    {
        return $this->hasMany(RunnerTemplate::class);
    }

    public function githubAccount(): BelongsTo
    {
        return $this->belongsTo(GitHubAccount::class, 'github_account_id');
    }

    public function pools(): HasMany
    {
        return $this->hasMany(Pool::class);
    }

    public function runners(): HasMany
    {
        return $this->hasMany(Runner::class);
    }

    public function imageBuilds(): HasMany
    {
        return $this->hasMany(ImageBuild::class);
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    protected function webhookUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $appUrl = app(SettingsRepository::class)->get('app_url', config('app.url'));

            return rtrim((string) $appUrl, '/').'/webhook/'.$this->githubAccount->webhook_id;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /**
     * Resolve the pool whose labels satisfy every label a job asked for.
     *
     * GitHub sends the job's `labels` array; a pool matches when that array is a
     * subset of the pool's labels. The most specific pool wins, mirroring the
     * The pool with the smallest matching label set wins.
     *
     * @param  array<int, string>  $labels
     */
    public function poolForLabels(array $labels): ?Pool
    {
        if ($labels === []) {
            return null;
        }

        $wanted = array_map('strtolower', $labels);

        if (! in_array('self-hosted', $wanted, true)
            || count(array_filter($wanted, fn (string $label): bool => $label !== 'self-hosted')) < 1) {
            return null;
        }

        return $this->pools()
            ->where('enabled', true)
            ->get()
            ->filter(function (Pool $pool) use ($wanted): bool {
                $available = array_map('strtolower', $pool->labels);

                return array_diff($wanted, $available) === [];
            })
            ->sortBy(fn (Pool $pool): int => count($pool->labels))
            ->first();
    }
}
