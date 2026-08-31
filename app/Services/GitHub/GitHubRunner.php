<?php

namespace App\Services\GitHub;

readonly class GitHubRunner
{
    public function __construct(
        public int $id,
        public string $name,
        public string $status,
        public bool $busy,
    ) {}

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            id: (int) ($payload['id'] ?? 0),
            name: (string) ($payload['name'] ?? ''),
            status: (string) ($payload['status'] ?? 'offline'),
            busy: (bool) ($payload['busy'] ?? false),
        );
    }
}
