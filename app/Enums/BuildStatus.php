<?php

namespace App\Enums;

enum BuildStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isFinished(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Cancelled], true);
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function colour(): string
    {
        return match ($this) {
            self::Queued => 'secondary',
            self::Running => 'blue',
            self::Succeeded => 'green',
            self::Failed => 'red',
            self::Cancelled => 'orange',
        };
    }
}
