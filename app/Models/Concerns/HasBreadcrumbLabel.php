<?php

namespace App\Models\Concerns;

/**
 * Supplies the human readable name a model is shown under in breadcrumbs.
 */
trait HasBreadcrumbLabel
{
    public function getBreadcrumbLabel(): string
    {
        $label = $this->getAttribute('name')
            ?? $this->getAttribute('title')
            ?? $this->getAttribute($this->getRouteKeyName())
            ?? $this->getKey();

        return (string) $label;
    }
}
