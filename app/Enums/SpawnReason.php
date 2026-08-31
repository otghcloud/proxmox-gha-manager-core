<?php

namespace App\Enums;

/**
 * Why a runner VM was created, recorded once and never changed. A warm runner that later picks up
 * a job still reads as `Warm`; the job it served is tracked separately.
 */
enum SpawnReason: string
{
    case Job = 'job';
    case Warm = 'warm';

    public function label(): string
    {
        return match ($this) {
            self::Job => 'On demand',
            self::Warm => 'Warm pool',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::Job => 'purple',
            self::Warm => 'teal',
        };
    }
}
