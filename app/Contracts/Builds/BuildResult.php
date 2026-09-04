<?php

namespace App\Contracts\Builds;

final readonly class BuildResult
{
    public function __construct(
        public bool $successful,
        public ?int $exitCode = null,
        public ?int $templateVmid = null,
    ) {}
}
