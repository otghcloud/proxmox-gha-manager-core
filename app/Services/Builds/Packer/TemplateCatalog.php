<?php

namespace App\Services\Builds\Packer;

use App\Services\SettingsRepository;

/**
 * Reads the `templates.json` catalog published by proxmox-gha-manager-templates.
 */
class TemplateCatalog
{
    public function path(): string
    {
        return $this->root().'/templates.json';
    }

    /**
     * The bundled image-builder path is baked into the container image at build time and is
     * read-only; a downloaded update on the persistent volume takes priority once activated.
     */
    public function root(): string
    {
        $active = $this->activeVersion();

        if (is_string($active) && $active !== '') {
            $downloaded = rtrim((string) config('builds.templates_install_path'), '/').'/'.$active;

            if (is_dir($downloaded)) {
                return $downloaded;
            }
        }

        return rtrim((string) config('builds.image_builder_path'), '/');
    }

    /**
     * Settings may be unreachable in contexts with no database (e.g. unit tests, early boot);
     * fall back to the baked-in path rather than blowing up catalog resolution.
     */
    private function activeVersion(): ?string
    {
        try {
            return app(SettingsRepository::class)->get(SettingsRepository::TEMPLATE_ACTIVE_VERSION);
        } catch (\Throwable) {
            return null;
        }
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
                fn (mixed $entry): bool => is_array($entry) && is_string($entry['id'] ?? null) && is_array($entry['builders'] ?? null),
            )
        ));
    }

    /**
     * The absolute directory holding the builder's files, or null when it is not installed.
     */
    public function templateDirectory(TemplateCatalogEntry $entry): ?string
    {
        $directory = $this->root().'/'.trim($entry->builderPath(), '/');

        return is_dir($directory) ? $directory : null;
    }

    /**
     * The builder's stage manifest, which drives build progress reporting.
     *
     * @return array<string, mixed>
     */
    public function buildManifest(TemplateCatalogEntry $entry): array
    {
        $path = $this->root().'/'.ltrim($entry->buildManifestPath(), '/');

        if (! is_readable($path)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        return is_array($manifest) ? $manifest : [];
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
     * The aggregate version of the currently active template bundle, whether downloaded or baked in.
     */
    public function imageBuilderVersion(): ?string
    {
        $version = $this->catalog()['image_builder_version'] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
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
