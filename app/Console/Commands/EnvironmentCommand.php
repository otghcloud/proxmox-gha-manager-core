<?php

namespace App\Console\Commands;

use App\Models\Environment;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

abstract class EnvironmentCommand extends Command
{
    /**
     * Resolve the environments this invocation applies to.
     *
     * @return Collection<int, Environment>
     */
    protected function environments(): Collection
    {
        $slug = $this->option('environment');

        $query = Environment::query()->where('enabled', true)->orderBy('name');

        if (is_string($slug) && $slug !== '') {
            $query->where('slug', $slug);
        }

        $environments = $query->get();

        if ($environments->isEmpty()) {
            $this->warn(is_string($slug) && $slug !== ''
                ? "No enabled environment matches '{$slug}'."
                : 'No enabled environments are configured.');
        }

        return $environments;
    }
}
