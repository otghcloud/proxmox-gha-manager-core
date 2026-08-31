<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsRepository
{
    private const CACHE_KEY = 'app.settings';

    /** Feature toggles that default to on when no row has been written yet. */
    public const REAPING_ENABLED = 'reaping_enabled';

    public const AUTO_SPAWN_ENABLED = 'auto_spawn_enabled';

    /** 'auto' deletes a superseded template as soon as nothing clones from it. */
    public const TEMPLATE_RETENTION_MODE = 'template_retention_mode';

    public const TEMPLATE_RETENTION_GENERATIONS = 'template_retention_generations';

    public const RETENTION_AUTO = 'auto';

    public const RETENTION_KEEP_LAST_N = 'keep_last_n';

    /** Days a stored workflow job log is kept before it is deleted from disk. */
    public const JOB_LOG_RETENTION_DAYS = 'job_log_retention_days';

    public const TEMPLATE_AUTO_CHECK_ENABLED = 'template_auto_check_enabled';

    public const TEMPLATE_CHECK_INTERVAL_HOURS = 'template_check_interval_hours';

    public const TEMPLATE_UPDATES_AVAILABLE = 'template_updates_available';

    public function templateAutoCheckEnabled(): bool
    {
        return $this->bool(self::TEMPLATE_AUTO_CHECK_ENABLED, false);
    }

    public function templateCheckIntervalHours(): int
    {
        return max(1, (int) $this->get(self::TEMPLATE_CHECK_INTERVAL_HOURS, 24));
    }

    public function jobLogRetentionDays(): int
    {
        return max(1, (int) $this->get(self::JOB_LOG_RETENTION_DAYS, 14));
    }

    public function templateRetentionMode(): string
    {
        return $this->get(self::TEMPLATE_RETENTION_MODE) === self::RETENTION_KEEP_LAST_N
            ? self::RETENTION_KEEP_LAST_N
            : self::RETENTION_AUTO;
    }

    public function templateRetentionGenerations(): int
    {
        return max(0, (int) $this->get(self::TEMPLATE_RETENTION_GENERATIONS, 1));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function bool(string $key, bool $default = true): bool
    {
        $value = $this->get($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);

        $this->flush();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->flush();
    }

    public function isInstalled(): bool
    {
        return (bool) $this->get('installed_at');
    }

    /**
     * The timezone timestamps are rendered in. Storage always stays in the framework timezone.
     */
    public function displayTimezone(): string
    {
        $timezone = $this->get('timezone');

        return is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : config('app.timezone', 'UTC');
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        // Deliberately not memoised on the instance: queue workers live for an hour and would
        // otherwise never see a setting changed from the web UI.
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'key')->all()
        );
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
