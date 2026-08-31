<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Request;

class BreadcrumbHelpers
{
    /**
     * Build breadcrumbs from the current URL path, humanising each segment.
     *
     * Numeric segments are replaced with the preceding segment's singular form so
     * `/environments/4/pools` reads "Environments / Environment / Pools".
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

        foreach ($segments as $index => $segment) {
            $path .= '/'.$segment;
            $isLast = $index === $count - 1;

            $crumbs[] = [
                'label' => self::label($segment, $segments[$index - 1] ?? null),
                'href' => $isLast ? null : url($path),
                'active' => $isLast,
            ];
        }

        return $crumbs;
    }

    private static function label(string $segment, ?string $previous): string
    {
        if (ctype_digit($segment)) {
            return $previous === null
                ? $segment
                : str($previous)->singular()->headline()->toString();
        }

        return str($segment)->headline()->toString();
    }
}
