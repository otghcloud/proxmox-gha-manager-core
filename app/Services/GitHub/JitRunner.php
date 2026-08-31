<?php

namespace App\Services\GitHub;

/**
 * A single-use runner registration minted by GitHub.
 */
readonly class JitRunner
{
    public function __construct(
        public int $runnerId,
        public string $name,
        public string $encodedJitConfig,
    ) {}
}
