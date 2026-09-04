<?php

namespace OTGH\ProxmoxGHA\Packer;

use App\Services\Builds\BuilderRegistry;
use Illuminate\Support\ServiceProvider;

class PackerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PackerBuilder::class);

        $this->app->afterResolving(BuilderRegistry::class, function (BuilderRegistry $registry): void {
            $registry->register($this->app->make(PackerBuilder::class));
        });
    }
}
