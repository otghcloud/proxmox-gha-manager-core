<?php

namespace App\Enums;

enum JobConclusion: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Cancelled = 'cancelled';
    case Skipped = 'skipped';
    case TimedOut = 'timed_out';
    case ActionRequired = 'action_required';
    case Neutral = 'neutral';

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    public function colour(): string
    {
        return match ($this) {
            self::Success => 'green',
            self::Failure, self::TimedOut => 'red',
            self::Cancelled, self::Skipped, self::Neutral => 'secondary',
            self::ActionRequired => 'orange',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Success => 'fa-solid fa-circle-check',
            self::Failure, self::TimedOut => 'fa-solid fa-circle-xmark',
            self::Cancelled, self::Skipped => 'fa-solid fa-ban',
            self::ActionRequired => 'fa-solid fa-triangle-exclamation',
            self::Neutral => 'fa-solid fa-circle-minus',
        };
    }
}
