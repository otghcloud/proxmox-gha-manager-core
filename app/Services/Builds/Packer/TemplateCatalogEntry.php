<?php

namespace App\Services\Builds\Packer;

use App\Exceptions\ProvisioningException;

class TemplateCatalogEntry
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    public function id(): string
    {
        return $this->string('id');
    }

    public function target(): string
    {
        return $this->string('target');
    }

    public function name(): string
    {
        return $this->string('name');
    }

    public function version(): string
    {
        return $this->string('version');
    }

    public function description(): string
    {
        return $this->string('description');
    }

    public function platform(): string
    {
        return $this->string('platform');
    }

    /** @return array<string, mixed> */
    public function requirements(): array
    {
        return is_array($this->data['build_requirements'] ?? null) ? $this->data['build_requirements'] : [];
    }

    public function isBuildEnabled(): bool
    {
        return ($this->data['build_capability']['enabled'] ?? false) === true;
    }

    public function disabledReason(): ?string
    {
        $reason = $this->data['build_capability']['disabled_reason'] ?? null;

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    public function runnerImagesDirectory(): string
    {
        return $this->string('packer.runner_images_directory');
    }

    public function requiresScriptsRoot(): bool
    {
        return ($this->data['packer']['scripts_root_required'] ?? false) === true;
    }

    public function communicator(): string
    {
        return $this->string('build_capability.communicator');
    }

    public function templatePath(): string
    {
        return $this->string('template_path');
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
