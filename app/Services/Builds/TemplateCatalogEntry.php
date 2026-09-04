<?php

namespace App\Services\Builds;

use App\Exceptions\ProvisioningException;

class TemplateCatalogEntry
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private readonly array $data,
        private readonly ?string $selectedBuilder = null,
    ) {}

    public function id(): string
    {
        return $this->string('id');
    }

    public function name(): string
    {
        return $this->string('name');
    }

    public function version(): string
    {
        return $this->string('metadata.version');
    }

    public function description(): string
    {
        return $this->string('description');
    }

    public function platform(): string
    {
        return $this->string('platform.type');
    }

    public function builderName(): string
    {
        $builders = $this->builders();

        if ($this->selectedBuilder !== null) {
            if (! isset($builders[$this->selectedBuilder])) {
                throw new ProvisioningException('The template catalog entry has no '.$this->selectedBuilder.' builder.');
            }

            return $this->selectedBuilder;
        }

        foreach (['packer', 'cloudimg', 'prebuilt'] as $name) {
            if (isset($builders[$name])) {
                return $name;
            }
        }

        $first = array_key_first($builders);

        if ($first === null) {
            throw new ProvisioningException('The template catalog entry declares no builders.');
        }

        return (string) $first;
    }

    public function builderType(): string
    {
        return $this->string('builders.'.$this->builderName().'.type');
    }

    public function isBuildable(): bool
    {
        return ($this->builder()['buildable'] ?? false) === true;
    }

    public function disabledReason(): ?string
    {
        $reason = $this->builder()['disabled_reason'] ?? null;

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    public function runnerImagesDirectory(): string
    {
        return $this->string('builders.'.$this->builderName().'.provisioner.runner_images_directory');
    }

    public function requiresScriptsRoot(): bool
    {
        return ($this->builder()['provisioner']['scripts_root_required'] ?? false) === true;
    }

    /** @return array<string, mixed> */
    public function requirements(): array
    {
        $requirements = $this->builder()['build_requirements'] ?? null;

        return is_array($requirements) ? $requirements : [];
    }

    public function estimatedMinutes(): ?int
    {
        $minutes = $this->requirements()['estimated_minutes'] ?? null;

        return is_numeric($minutes) && (int) $minutes > 0 ? (int) $minutes : null;
    }

    public function builderPath(): string
    {
        return $this->string('builders.'.$this->builderName().'.path');
    }

    public function buildManifestPath(): string
    {
        return $this->string('builders.'.$this->builderName().'.build_manifest');
    }

    /** @return array<string, mixed> */
    public function builder(): array
    {
        $builder = $this->builders()[$this->builderName()] ?? null;

        return is_array($builder) ? $builder : [];
    }

    /** @return array<string, mixed> */
    public function builders(): array
    {
        return is_array($this->data['builders'] ?? null) ? $this->data['builders'] : [];
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }

    private function string(string $key): string
    {
        $value = data_get($this->data, $key);

        if (! is_string($value) || $value === '') {
            throw new ProvisioningException("The template catalog entry is missing {$key}.");
        }

        return $value;
    }
}
