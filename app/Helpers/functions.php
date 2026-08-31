<?php

use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Request;

if (! function_exists('nav_active')) {
    /**
     * Return the active class when the current request matches any of the given patterns.
     *
     * @param  string|array<int, string>  $patterns
     */
    function nav_active(string|array $patterns, string $class = ' active'): string
    {
        return Request::is($patterns) ? $class : '';
    }
}

if (! function_exists('setting')) {
    /**
     * Read an application setting from the database-backed settings store.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsRepository::class)->get($key, $default);
    }
}

if (! function_exists('app_version')) {
    /**
     * Get the deployed application version or dev-main.
     */
    function app_version(): string
    {
        $releaseFile = base_path('RELEASE');

        if (file_exists($releaseFile)) {
            $version = trim((string) file_get_contents($releaseFile));
            if ($version !== '') {
                return $version;
            }
        }

        return config('app.version', 'dev-main');
    }
}
