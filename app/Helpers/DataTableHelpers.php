<?php

namespace App\Helpers;

class DataTableHelpers
{
    /**
     * Render a standard DataTable actions dropdown.
     *
     * @param  array<int, array<string, mixed>>  $actions
     */
    public static function actionsDropdown(array $actions, string $buttonLabel = '<i class="fa-solid fa-fw fa-gear"></i>'): string
    {
        $itemsHtml = '';

        foreach ($actions as $action) {
            $action = self::normalizeAction($action);

            $label = (string) ($action['label'] ?? '');
            $href = (string) ($action['href'] ?? '#');

            if ($label === '') {
                continue;
            }

            $iconClass = (string) ($action['icon'] ?? '');
            $linkClass = trim('dropdown-item '.((string) ($action['class'] ?? '')));
            $attributes = $action['attributes'] ?? [];

            if (! is_array($attributes)) {
                $attributes = [];
            }

            $attrString = self::attributesToString(array_merge([
                'class' => $linkClass,
                'href' => $href,
            ], $attributes));

            $iconHtml = $iconClass !== ''
                ? '<i class="'.self::escape($iconClass).'"></i> '
                : '';

            $itemsHtml .= '<a '.$attrString.'>'.$iconHtml.self::escape($label).' </a>';
        }

        return '<button class="btn btn-sm dropdown-toggle align-text-top" data-bs-boundary="viewport" data-bs-toggle="dropdown" aria-expanded="false">'.($buttonLabel).'</button>'
            .'<div class="dropdown-menu dropdown-menu-end">'.$itemsHtml.'</div>';
    }

    /**
     * Normalize an action definition using preset defaults.
     * Supported types: view, edit, delete, custom.
     *
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private static function normalizeAction(array $action): array
    {
        $type = strtolower((string) ($action['type'] ?? 'custom'));

        $defaults = match ($type) {
            'view' => [
                'label' => 'View',
                'icon' => 'fa-solid fa-eye fa-fw',
                'class' => '',
                'href' => '#',
                'attributes' => [],
            ],
            'edit' => [
                'label' => 'Edit',
                'icon' => 'fa-solid fa-pencil fa-fw',
                'class' => 'text-warning',
                'href' => '#',
                'attributes' => [],
            ],
            'delete' => [
                'label' => 'Delete',
                'icon' => 'fa-solid fa-trash-can fa-fw',
                'class' => 'text-danger',
                'href' => '#',
                'attributes' => [
                    'data-action' => 'delete-modal',
                ],
            ],
            default => [
                'label' => '',
                'icon' => '',
                'class' => '',
                'href' => '#',
                'attributes' => [],
            ],
        };

        $attributes = $action['attributes'] ?? [];

        if (! is_array($attributes)) {
            $attributes = [];
        }

        $merged = array_merge($defaults, $action);
        $merged['attributes'] = array_merge($defaults['attributes'], $attributes);

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function attributesToString(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $parts[] = self::escape((string) $key);

                continue;
            }

            $parts[] = self::escape((string) $key).'="'.self::escape((string) $value).'"';
        }

        return implode(' ', $parts);
    }

    /**
     * A compact elapsed time such as `4s`, `2m 09s` or `1h 05m`.
     */
    public static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        if ($seconds < 60) {
            return $seconds.'s';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60).'m '.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT).'s';
        }

        return intdiv($seconds, 3600).'h '.str_pad((string) (intdiv($seconds, 60) % 60), 2, '0', STR_PAD_LEFT).'m';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
