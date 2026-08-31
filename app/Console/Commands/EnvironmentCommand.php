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
    protected function environments(bool $includeDisabled = false): Collection
    {
        $slug = $this->option('environment');

        $query = Environment::query()->orderBy('name');

        if (! $includeDisabled) {
            $query->where('enabled', true);
        }

        if (is_string($slug) && $slug !== '') {
            $query->where('slug', $slug);
        }

        $environments = $query->get();

        if ($environments->isEmpty()) {
            $status = $includeDisabled ? '' : 'enabled ';

            $this->warn(is_string($slug) && $slug !== ''
                ? "No {$status}environment matches '{$slug}'."
                : "No {$status}environments are configured.");
        }

        return $environments;
    }
}
