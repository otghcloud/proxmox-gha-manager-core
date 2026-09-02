<?php

namespace App\Services\Templates;

use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Downloads the latest `main` of proxmox-gha-manager-templates onto the persistent volume and
 * activates it, so an update survives without rebuilding the (otherwise read-only) container
 * image that ships with whatever was baked in at `docker build` time.
 */
class TemplateDownloadService
{
    private const LOCK_KEY = 'template-download';

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * Fetch, extract and activate the latest template bundle. Returns the installed version.
     *
     * @return array{version: string, path: string}
     */
    public function download(): array
    {
        return Cache::lock(self::LOCK_KEY, 120)->block(30, function (): array {
            $root = $this->installRoot();
            File::ensureDirectoryExists($root);

            $tmpId = (string) Str::uuid();
            $archive = $root.'/.tmp-'.$tmpId.'.tar.gz';
            $extractDir = $root.'/.tmp-'.$tmpId;

            try {
                $this->fetchArchive($archive);
                $extractedRoot = $this->extract($archive, $extractDir);
                $version = $this->readVersion($extractedRoot);

                $destination = $root.'/'.$version;

                if (! is_dir($destination)) {
                    rename($extractedRoot, $destination);
                }

                $this->activate($version);

                return ['version' => $version, 'path' => $destination];
            } finally {
                File::delete($archive);
                File::deleteDirectory($extractDir);
            }
        });
    }

    /**
     * Point the catalog resolver at an already-downloaded version and prune old ones.
     */
    public function activate(string $version): void
    {
        $path = $this->installRoot().'/'.$version;

        if (! is_file($path.'/templates.json')) {
            throw new RuntimeException("Template bundle {$version} is not installed.");
        }

        $this->settings->set(SettingsRepository::TEMPLATE_ACTIVE_VERSION, $version);

        $this->prune();
    }

    /**
     * Installed bundle versions, newest first, for the settings UI's rollback picker.
     *
     * @return array<int, array{version: string, downloaded_at: int, active: bool}>
     */
    public function installedVersions(): array
    {
        $root = $this->installRoot();
        $active = $this->settings->get(SettingsRepository::TEMPLATE_ACTIVE_VERSION);

        if (! is_dir($root)) {
            return [];
        }

        $versions = array_filter(scandir($root) ?: [], fn (string $entry): bool => $entry[0] !== '.' && is_dir($root.'/'.$entry));

        $entries = array_map(fn (string $version): array => [
            'version' => $version,
            'downloaded_at' => filemtime($root.'/'.$version) ?: 0,
            'active' => $version === $active,
        ], $versions);

        usort($entries, fn (array $a, array $b): int => version_compare($b['version'], $a['version']));

        return array_values($entries);
    }

    /**
     * Delete downloaded versions beyond the configured retention, always keeping the active one.
     */
    public function prune(): void
    {
        $keep = $this->settings->templateBundleRetentionMode() === SettingsRepository::RETENTION_KEEP_LAST_N
            ? $this->settings->templateBundleRetentionGenerations()
            : 0;

        $versions = $this->installedVersions();
        $inactive = array_values(array_filter($versions, fn (array $entry): bool => ! $entry['active']));

        foreach (array_slice($inactive, $keep) as $entry) {
            File::deleteDirectory($this->installRoot().'/'.$entry['version']);
        }
    }

    private function installRoot(): string
    {
        return rtrim((string) config('builds.templates_install_path'), '/');
    }

    private function fetchArchive(string $destination): void
    {
        $repository = rtrim((string) config('builds.templates_repository'), '/');
        $url = preg_replace('#^https://github\.com/#', 'https://codeload.github.com/', $repository).'/tar.gz/refs/heads/main';

        $response = Http::timeout(60)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Failed to download templates archive: HTTP {$response->status()}.");
        }

        File::put($destination, $response->body());
    }

    /**
     * GitHub tarballs contain a single top-level `{repo}-{ref}` directory; return its path.
     */
    private function extract(string $archive, string $extractDir): string
    {
        File::ensureDirectoryExists($extractDir);

        (new \PharData($archive))->extractTo($extractDir, overwrite: true);

        $entries = array_values(array_filter(scandir($extractDir) ?: [], fn (string $entry): bool => $entry[0] !== '.'));

        if (count($entries) !== 1 || ! is_dir($extractDir.'/'.$entries[0])) {
            throw new RuntimeException('Unexpected templates archive layout.');
        }

        return $extractDir.'/'.$entries[0];
    }

    private function readVersion(string $root): string
    {
        $path = $root.'/templates.json';

        if (! is_readable($path)) {
            throw new RuntimeException('Downloaded templates archive has no templates.json.');
        }

        $catalog = json_decode((string) file_get_contents($path), true);
        $version = is_array($catalog) ? ($catalog['image_builder_version'] ?? null) : null;

        if (! is_string($version) || $version === '') {
            throw new RuntimeException('Downloaded templates.json has no image_builder_version.');
        }

        return $version;
    }
}
