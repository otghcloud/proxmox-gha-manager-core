<?php

namespace App\Services\Builds\Packer;

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

    public function entryForId(?string $id): ?TemplateCatalogEntry
    {
        foreach ($this->templates() as $entry) {
            if (($entry['id'] ?? null) === $id) {
                return new TemplateCatalogEntry($entry);
            }
        }

        return null;
    }

    public function entryForTarget(?string $target): ?TemplateCatalogEntry
    {
        foreach ($this->templates() as $entry) {
            if (($entry['target'] ?? null) === $target) {
                return new TemplateCatalogEntry($entry);
            }
        }

        return null;
    }

    /**
     * The template metadata published with the installed image-builder bundle.
     *
     * @return array<int, array<string, mixed>>
     */
    public function templates(): array
    {
        return array_values(array_map(
            fn (array $entry): array => $this->hydrate($entry),
            array_filter(
                $this->catalog()['templates'] ?? [],
                fn (mixed $entry): bool => is_array($entry) && is_string($entry['id'] ?? null) && is_string($entry['target'] ?? null),
            )
        ));
    }

    /**
     * The absolute directory holding the target's Packer files, or null when it is not installed.
     */
    public function templateDirectory(TemplateCatalogEntry $entry): ?string
    {
        $directory = $this->root().'/'.trim($entry->templatePath(), '/');

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

    /** @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function hydrate(array $entry): array
    {
        $path = $entry['template_json_path'] ?? null;

        if (! is_string($path) || $path === '') {
            return $entry;
        }

        $metadata = $this->root().'/'.ltrim($path, '/');
        $full = is_readable($metadata) ? json_decode((string) file_get_contents($metadata), true) : null;

        return is_array($full) ? array_merge($entry, $full) : $entry;
    }
}
