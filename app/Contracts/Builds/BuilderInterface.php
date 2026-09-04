<?php

namespace App\Contracts\Builds;

use App\Models\ImageBuild;
use App\Services\Builds\TemplateCatalogEntry;

interface BuilderInterface
{
    public function type(): string;

    public function build(
        ImageBuild $build,
        TemplateCatalogEntry $entry,
        string $templateDirectory,
    ): BuildResult;
}
