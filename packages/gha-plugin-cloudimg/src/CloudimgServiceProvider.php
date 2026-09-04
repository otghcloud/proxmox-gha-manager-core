<?php

namespace OTGH\ProxmoxGHA\Cloudimg;

use App\Services\Builds\BuilderRegistry;
use Illuminate\Support\ServiceProvider;

class CloudimgServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CloudimgBuilder::class);

        $this->app->afterResolving(BuilderRegistry::class, function (BuilderRegistry $registry): void {
            $registry->register($this->app->make(CloudimgBuilder::class));
        });
    }
}
