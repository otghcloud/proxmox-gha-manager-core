<?php

namespace App\Services\Templates;

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

            $localPath = rtrim(config('builds.image_builder_path'), '/').'/templates.json';
            $localTemplates = [];

            if (file_exists($localPath)) {
                $localCatalog = json_decode((string) file_get_contents($localPath), true);
                if (is_array($localCatalog) && is_array($localCatalog['templates'] ?? null)) {
                    foreach ($localCatalog['templates'] as $t) {
                        if (isset($t['id'])) {
                            $localTemplates[$t['id']] = $t['version'] ?? '0.0.0';
                        }
                    }
                }
            }

            $updates = [];
            $remoteVersions = [];
            foreach ($remoteCatalog['templates'] as $remote) {
                $id = $remote['id'] ?? null;
                $target = $remote['target'] ?? null;
                $remoteVersion = $remote['version'] ?? '0.0.0';
                $localVersion = $localTemplates[$id] ?? '0.0.0';

                if ($target && $remoteVersion !== '0.0.0') {
                    $remoteVersions[$target] = $remoteVersion;
                }

                if ($id && version_compare($remoteVersion, $localVersion, '>')) {
                    $updates[] = [
                        'id' => $id,
                        'name' => $remote['name'] ?? $id,
                        'target' => $target ?? '',
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
     * Get local template version for a specific build target from local templates.json.
     */
    public static function getLocalVersionForTarget(?string $target): ?string
    {
        if (! $target) {
            return null;
        }

        $localPath = rtrim(config('builds.image_builder_path'), '/').'/templates.json';
        if (! file_exists($localPath) || ! is_readable($localPath)) {
            return null;
        }

        $catalog = json_decode((string) file_get_contents($localPath), true);
        if (! is_array($catalog) || ! is_array($catalog['templates'] ?? null)) {
            return null;
        }

        foreach ($catalog['templates'] as $t) {
            if (is_array($t) && ($t['target'] ?? null) === $target && ! empty($t['version'])) {
                return (string) $t['version'];
            }
        }

        return null;
    }

    /**
     * Check if a remote update exists for the given target, comparing against current version.
     */
    public function getAvailableUpdateVersion(?string $target, ?string $currentVersion = null): ?string
    {
        if (! $target || ! $this->settings->templateAutoCheckEnabled()) {
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

        $remoteVersion = $data['remote_versions'][$target] ?? null;
        if (! $remoteVersion) {
            foreach ($data['updates'] ?? [] as $up) {
                if (($up['target'] ?? null) === $target) {
                    $remoteVersion = $up['new_version'] ?? null;
                    break;
                }
            }
        }

        if (! $remoteVersion) {
            return null;
        }

        $effectiveCurrent = $currentVersion ?: self::getLocalVersionForTarget($target) ?: '0.0.0';

        return version_compare($remoteVersion, $effectiveCurrent, '>') ? $remoteVersion : null;
    }
}
