<?php

namespace OTGH\ProxmoxGHA\Prebuilt;

use App\Services\Builds\BuilderRegistry;
use Illuminate\Support\ServiceProvider;

class PrebuiltServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PrebuiltBuilder::class);

        $this->app->afterResolving(BuilderRegistry::class, function (BuilderRegistry $registry): void {
            $registry->register($this->app->make(PrebuiltBuilder::class));
        });
    }
}
