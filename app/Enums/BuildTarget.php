<?php

namespace App\Enums;

/**
 * Proxmox build targets accepted by image-builder's `scripts/build.sh`.
 *
 * QEMU targets are deliberately absent: they build locally and remain exclusive to the
 * standalone image-builder, whereas Proxmox builds run on the Proxmox host over its API.
 */
enum BuildTarget: string
{
    case Ubuntu2404 = 'pmx-ubuntu2404';
    case Ubuntu2604 = 'pmx-ubuntu2604';
    case UbuntuSlim = 'pmx-ubuntu-slim';
    case Windows2022 = 'pmx-windows2022';
    case Windows2025 = 'pmx-windows2025';

    public function label(): string
    {
        return match ($this) {
            self::Ubuntu2404 => 'Ubuntu 24.04',
            self::Ubuntu2604 => 'Ubuntu 26.04',
            self::UbuntuSlim => 'Ubuntu slim',
            self::Windows2022 => 'Windows Server 2022',
            self::Windows2025 => 'Windows Server 2025',
        };
    }

    public function os(): PoolOs
    {
        return match ($this) {
            self::Windows2022, self::Windows2025 => PoolOs::Windows,
            default => PoolOs::Linux,
        };
    }

    /**
     * The Packer variable carrying this target's installation ISO.
     */
    public function isoVariable(): string
    {
        return match ($this) {
            self::Ubuntu2404 => 'PKR_VAR_pmx_ubuntu2404_iso_file',
            self::Ubuntu2604 => 'PKR_VAR_pmx_ubuntu2604_iso_file',
            self::UbuntuSlim => 'PKR_VAR_pmx_ubuntu_slim_iso_file',
            self::Windows2022 => 'PKR_VAR_pmx_windows2022_iso_file',
            self::Windows2025 => 'PKR_VAR_pmx_windows2025_iso_file',
        };
    }

    /**
     * Packer variables for build-time sizing, which differ between the Ubuntu and Windows templates.
     *
     * @return array{cores: string, memory: string, disk: string}
     */
    public function sizingVariables(): array
    {
        $prefix = $this->os() === PoolOs::Windows ? 'windows' : 'ubuntu';

        return [
            'cores' => "PKR_VAR_{$prefix}_cpu_cores",
            'memory' => "PKR_VAR_{$prefix}_memory_mb",
            'disk' => "PKR_VAR_{$prefix}_disk_size_gb",
        ];
    }

    public function isSupported(): bool
    {
        return $this->os() === PoolOs::Linux;
    }
}
