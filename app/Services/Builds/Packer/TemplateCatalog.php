<?php

namespace App\Services\Builds\Packer;

use App\Enums\BuildTarget;

/**
 * Reads the `templates.json` catalog published by proxmox-gha-manager-templates.
 */
class TemplateCatalog
{
    public function path(): string
    {
        return $this->root().'/templates.json';
    }

    public function root(): string
    {
        return rtrim((string) config('builds.image_builder_path'), '/');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function entryFor(BuildTarget $target): ?array
    {
        foreach ($this->catalog()['templates'] ?? [] as $entry) {
            if (is_array($entry) && ($entry['target'] ?? null) === $target->value) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * The absolute directory holding the target's Packer files, or null when it is not installed.
     */
    public function templateDirectory(BuildTarget $target): ?string
    {
        $entry = $this->entryFor($target);
        $relative = $entry['template_path'] ?? null;

        if (! is_string($relative) || $relative === '') {
            return null;
        }

        $directory = $this->root().'/'.trim($relative, '/');

        return is_dir($directory) ? $directory : null;
    }

    /**
     * The upstream actions/runner-images commit these templates were generated against.
     */
    public function runnerImagesCommit(): ?string
    {
        $commit = $this->catalog()['runner_images_commit'] ?? null;

        return is_string($commit) && preg_match('/^[0-9a-f]{40}$/', $commit) === 1 ? $commit : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $path = $this->path();

        if (! is_readable($path)) {
            return [];
        }

        $catalog = json_decode((string) file_get_contents($path), true);

        return is_array($catalog) ? $catalog : [];
    }
}
