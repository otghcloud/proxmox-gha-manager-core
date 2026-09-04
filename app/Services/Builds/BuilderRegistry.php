<?php

namespace App\Services\Builds;

use App\Contracts\Builds\BuilderInterface;
use App\Exceptions\ProvisioningException;

final class BuilderRegistry
{
    /** @var array<string, BuilderInterface> */
    private array $builders = [];

    public function register(BuilderInterface $builder): void
    {
        $type = $builder->type();

        if (isset($this->builders[$type])) {
            throw new ProvisioningException('A builder is already registered for '.$type.'.');
        }

        $this->builders[$type] = $builder;
    }

    public function forType(string $type): BuilderInterface
    {
        $builder = $this->builders[$type] ?? null;

        if ($builder === null) {
            throw new ProvisioningException('No builder is registered for '.$type.'.');
        }

        return $builder;
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->builders);
    }
}
