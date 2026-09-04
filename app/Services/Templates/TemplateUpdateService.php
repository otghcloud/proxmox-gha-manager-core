<?php

namespace App\Services\Templates;

use App\Services\Builds\TemplateCatalog;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TemplateUpdateService
{
    public const REMOTE_URL = 'https://raw.githubusercontent.com/otghcloud/proxmox-gha-manager-templates/refs/heads/main/templates.json';

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * Check remote templates.json against local catalog.
     *
     * @return array<string, mixed>
     */
    public function checkForUpdates(): array
    {
        try {
            $response = Http::timeout(15)->get(self::REMOTE_URL);

            if (! $response->successful()) {
                Log::warning('Failed to fetch remote template index', ['status' => $response->status()]);

                return ['available' => false, 'updates' => []];
            }

            $remoteCatalog = $response->json();
            if (! is_array($remoteCatalog) || ! is_array($remoteCatalog['templates'] ?? null)) {
                return ['available' => false, 'updates' => []];
            }

            $localPath = (new TemplateCatalog)->root().'/templates.json';
            $localTemplates = [];

            if (file_exists($localPath)) {
                $localCatalog = json_decode((string) file_get_contents($localPath), true);
                if (is_array($localCatalog) && is_array($localCatalog['templates'] ?? null)) {
                    foreach ($localCatalog['templates'] as $t) {
                        if (isset($t['id'])) {
                            $localTemplates[$t['id']] = $t['metadata']['version'] ?? '0.0.0';
                        }
                    }
                }
            }

            $updates = [];
            $remoteVersions = [];
            foreach ($remoteCatalog['templates'] as $remote) {
                $id = $remote['id'] ?? null;
                $remoteVersion = $remote['metadata']['version'] ?? '0.0.0';
                $localVersion = $localTemplates[$id] ?? '0.0.0';

                if ($id && $remoteVersion !== '0.0.0') {
                    $remoteVersions[$id] = $remoteVersion;
                }

                if ($id && version_compare($remoteVersion, $localVersion, '>')) {
                    $updates[] = [
                        'id' => $id,
                        'name' => $remote['name'] ?? $id,
                        'current_version' => $localVersion,
                        'new_version' => $remoteVersion,
                    ];
                }
            }

            $result = [
                'available' => count($updates) > 0,
                'checked_at' => now()->toIso8601String(),
                'updates' => $updates,
                'remote_versions' => $remoteVersions,
            ];

            $this->settings->set(SettingsRepository::TEMPLATE_UPDATES_AVAILABLE, json_encode($result));

            return $result;
        } catch (\Throwable $e) {
            Log::error('Error checking for template updates', ['error' => $e->getMessage()]);

            return ['available' => false, 'updates' => []];
        }
    }

    /**
     * Get local template version for a specific immutable catalog ID.
     */
    public static function getLocalVersionForId(?string $id): ?string
    {
        if (! $id) {
            return null;
        }

        $localPath = (new TemplateCatalog)->root().'/templates.json';
        if (! file_exists($localPath) || ! is_readable($localPath)) {
            return null;
        }

        $catalog = json_decode((string) file_get_contents($localPath), true);
        if (! is_array($catalog) || ! is_array($catalog['templates'] ?? null)) {
            return null;
        }

        foreach ($catalog['templates'] as $t) {
            if (is_array($t) && ($t['id'] ?? null) === $id && ! empty($t['metadata']['version'])) {
                return (string) $t['metadata']['version'];
            }
        }

        return null;
    }

    /**
     * Used only by historical migrations created before catalog IDs existed.
     */
    public static function getLocalVersionForTarget(?string $target): ?string
    {
        if (! $target) {
            return null;
        }

        $path = (new TemplateCatalog)->root().'/templates.json';
        $catalog = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;

        foreach ($catalog['templates'] ?? [] as $entry) {
            if (is_array($entry) && ($entry['target'] ?? null) === $target) {
                return is_string($entry['version'] ?? null) ? $entry['version'] : null;
            }
        }

        return null;
    }

    /**
     * Check if a remote update exists for the given catalog ID, comparing against current version.
     */
    public function getAvailableUpdateVersion(?string $id, ?string $currentVersion = null): ?string
    {
        if (! $id || ! $this->settings->templateAutoCheckEnabled()) {
            return null;
        }

        $raw = $this->settings->get(SettingsRepository::TEMPLATE_UPDATES_AVAILABLE);
        if (! $raw) {
            return null;
        }

        $data = json_decode((string) $raw, true);
        if (! is_array($data)) {
            return null;
        }

        $remoteVersion = $data['remote_versions'][$id] ?? null;
        if (! $remoteVersion) {
            foreach ($data['updates'] ?? [] as $up) {
                if (($up['id'] ?? null) === $id) {
                    $remoteVersion = $up['new_version'] ?? null;
                    break;
                }
            }
        }

        if (! $remoteVersion) {
            return null;
        }

        $effectiveCurrent = $currentVersion ?: self::getLocalVersionForId($id) ?: '0.0.0';

        return version_compare($remoteVersion, $effectiveCurrent, '>') ? $remoteVersion : null;
    }
}
