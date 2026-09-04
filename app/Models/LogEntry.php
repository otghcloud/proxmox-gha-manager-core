<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A stored copy of a log produced elsewhere (a Packer build, a GitHub job), kept once the
 * originating file is no longer guaranteed to exist.
 *
 * Named LogEntry rather than Log to avoid colliding with the Log facade.
 */
class LogEntry extends Model
{
    public const CHANNEL_BUILD = 'build';

    public const CHANNEL_JOB = 'job';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Stores (or replaces) the log for a record, so a retried fetch does not duplicate rows.
     */
    public static function store(Model $loggable, string $channel, string $body): self
    {
        return static::updateOrCreate(
            [
                'loggable_type' => $loggable->getMorphClass(),
                'loggable_id' => $loggable->getKey(),
                'channel' => $channel,
            ],
            [
                'body' => $body,
                'byte_size' => strlen($body),
                'recorded_at' => now(),
            ],
        );
    }
}
