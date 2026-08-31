<?php

namespace App\Support;

use App\Enums\PoolOs;

/**
 * Suggested JIT label sets, keyed by the template name conventions used by image-builder.
 *
 * JIT runners receive only the labels configured here, so a mismatch is the most common
 * reason a queued job never gets picked up. Offering known-good sets from the supported
 * config.example.yaml keeps hand-typed mistakes down.
 */
class LabelPresets
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function forOs(PoolOs $os): array
    {
        return match ($os) {
            PoolOs::Linux => [
                'Ubuntu 24.04' => ['self-hosted', 'linux', 'x64', 'ubuntu-24.04', 'ubuntu-latest'],
                'Ubuntu 26.04' => ['self-hosted', 'linux', 'x64', 'ubuntu-26.04'],
                'Ubuntu slim' => ['self-hosted', 'linux', 'x64', 'ubuntu-slim'],
            ],
            PoolOs::Windows => [
                'Windows Server 2022' => ['self-hosted', 'windows', 'x64', 'windows-2022', 'windows-latest'],
                'Windows Server 2025' => ['self-hosted', 'windows', 'x64', 'windows-2025'],
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function baseline(PoolOs $os): array
    {
        return ['self-hosted', $os->value, 'x64'];
    }

    /**
     * Every preset, grouped by OS, for rendering in the pool form.
     *
     * @return array<string, array<string, array<int, string>>>
     */
    public static function all(): array
    {
        $presets = [];

        foreach (PoolOs::cases() as $os) {
            $presets[$os->value] = self::forOs($os);
        }

        return $presets;
    }
}
