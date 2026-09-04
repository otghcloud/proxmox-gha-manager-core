<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class BreadcrumbHelpers
{
    /**
     * Build breadcrumbs from the current URL path, humanising each segment.
     *
     * Segments that the router resolved to a model are replaced with that record's own label,
     * so `/workflows/runners/12` reads "Home / Workflows / Runners / gha-mtn5sbymd0nvbpji".
     *
     * @return array<int, array{label: string, href: string|null, active: bool}>
     */
    public static function forRequest(?Request $request = null): array
    {
        $segments = request()->segments();

        if ($segments === []) {
            return [];
        }

        $crumbs = [[
            'label' => 'Home',
            'href' => url('/'),
            'active' => false,
        ]];

        $path = '';
        $count = count($segments);
        $bound = self::boundModels();

        foreach ($segments as $index => $segment) {
            $path .= '/'.$segment;
            $isLast = $index === $count - 1;

            $crumbs[] = [
                'label' => self::label($segment, $segments[$index - 1] ?? null, $bound),
                'href' => $isLast ? null : url($path),
                'active' => $isLast,
            ];
        }

        return $crumbs;
    }

    /**
     * Models already resolved by route model binding, keyed by the segment they appear as.
     *
     * @return array<string, Model>
     */
    private static function boundModels(): array
    {
        $models = [];

        foreach (request()->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                $models[(string) $parameter->getRouteKey()] = $parameter;
            }
        }

        return $models;
    }

    /**
     * @param  array<string, Model>  $bound
     */
    private static function label(string $segment, ?string $previous, array $bound): string
    {
        $model = $bound[$segment] ?? null;

        if ($model !== null && method_exists($model, 'getBreadcrumbLabel')) {
            $label = trim($model->getBreadcrumbLabel());

            if ($label !== '') {
                return $label;
            }
        }

        if (ctype_digit($segment)) {
            return $previous === null
                ? $segment
                : str($previous)->singular()->headline()->toString();
        }

        return str($segment)->headline()->toString();
    }
}
