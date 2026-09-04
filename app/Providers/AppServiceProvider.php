<?php

namespace App\Providers;

use App\Services\Builds\BuilderRegistry;
use App\Services\SettingsRepository;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BuilderRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Timestamps are written and compared in the framework timezone everywhere. The configured
        // timezone is display only, so a long-running queue worker can never drift from the web
        // process the way a runtime date_default_timezone_set() would.
        Carbon::macro('forDisplay', function (): CarbonInterface {
            /** @var CarbonInterface $this */
            return $this->copy()->setTimezone(app(SettingsRepository::class)->displayTimezone());
        });
    }
}
