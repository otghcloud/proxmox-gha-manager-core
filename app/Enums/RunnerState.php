<?php

namespace App\Enums;

enum RunnerState: string
{
    case Spawning = 'spawning';
    case Idle = 'idle';
    case Busy = 'busy';
    case Reaping = 'reaping';
    case Failed = 'failed';
    case Destroyed = 'destroyed';

    /**
     * States that count towards capacity limits.
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::Spawning, self::Idle, self::Busy];
    }

    /**
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return array_map(fn (self $state): string => $state->value, self::active());
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function colour(): string
    {
        return match ($this) {
            self::Spawning => 'azure',
            self::Idle => 'green',
            self::Busy => 'blue',
            self::Reaping => 'orange',
            self::Failed => 'red',
            self::Destroyed => 'secondary',
        };
    }
}
