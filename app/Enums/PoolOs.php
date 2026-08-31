<?php

namespace App\Enums;

enum PoolOs: string
{
    case Linux = 'linux';
    case Windows = 'windows';

    public function defaultRunnerDir(): string
    {
        return match ($this) {
            self::Linux => '/opt/actions-runner',
            self::Windows => 'C:\\actions-runner',
        };
    }

    public function remotePort(): int
    {
        return match ($this) {
            self::Linux => 22,
            self::Windows => 5985,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Linux => 'Linux',
            self::Windows => 'Windows',
        };
    }
}
