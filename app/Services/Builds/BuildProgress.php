<?php

namespace App\Services\Builds;

use App\Enums\BuildStatus;
use App\Models\ImageBuild;

class BuildProgress
{
    public function __construct(private readonly TemplateCatalog $catalog = new TemplateCatalog) {}

    /**
     * @return array<string, mixed>
     */
    public function forBuild(ImageBuild $build): array
    {
        $entry = $this->catalog->entryForId($build->template_catalog_id);

        if ($entry === null) {
            return ['available' => false];
        }

        $manifest = $this->catalog->buildManifest($entry);

        $stages = $this->stages($manifest);

        if ($stages === []) {
            return ['available' => false];
        }

        $seen = $this->seenStageIds($build);
        $lastSeen = $seen === [] ? null : end($seen);
        $finished = $build->status->isFinished();
        $succeeded = $build->status === BuildStatus::Succeeded;
        $stageCount = count($stages);
        $seenCount = count(array_intersect(array_column($stages, 'id'), $seen));
        $currentStage = null;
        $estimatedMinutes = $entry->estimatedMinutes();

        $stageStates = array_map(function (array $stage) use ($seen, $lastSeen, $finished, $succeeded, &$currentStage): array {
            $id = (string) ($stage['id'] ?? '');
            $isSeen = in_array($id, $seen, true);
            $isCurrent = $id !== '' && $id === $lastSeen && ! $finished;

            if ($isCurrent) {
                $state = 'current';
                $currentStage = $stage;
            } elseif ($isSeen || $succeeded) {
                $state = 'complete';
            } else {
                $state = 'pending';
            }

            return [
                'id' => $id,
                'name' => (string) ($stage['name'] ?? $id),
                'category' => (string) ($stage['category'] ?? 'build'),
                'category_label' => (string) ($stage['category_label'] ?? 'Build'),
                'state' => $state,
            ];
        }, $stages);

        $percent = $succeeded
            ? 100
            : ($stageCount > 0 ? (int) floor(($seenCount / $stageCount) * 100) : 0);

        return [
            'available' => true,
            'target' => $entry->id(),
            'name' => $entry->name(),
            'estimated_minutes' => $estimatedMinutes,
            'estimated_duration' => $this->formatEstimatedMinutes($estimatedMinutes),
            'stage_count' => $stageCount,
            'completed_count' => $succeeded ? $stageCount : $seenCount,
            'percent' => $percent,
            'status_label' => match ($build->status) {
                BuildStatus::Succeeded => 'Build completed',
                BuildStatus::Failed => 'Build failed',
                BuildStatus::Cancelled => 'Build cancelled',
                default => null,
            },
            'current_stage' => $currentStage === null ? null : [
                'id' => (string) ($currentStage['id'] ?? ''),
                'name' => (string) ($currentStage['name'] ?? ''),
            ],
            'stages' => $stageStates,
            'groups' => $this->groups($stageStates),
        ];
    }

    /**
     * Stages are declared by the builder's manifest, flattened in display order.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<int, array<string, mixed>>
     */
    private function stages(array $manifest): array
    {
        $groups = $manifest['stage_groups'] ?? [];

        if (! is_array($groups)) {
            return [];
        }

        usort($groups, fn (array $a, array $b): int => ($a['display_order'] ?? 0) <=> ($b['display_order'] ?? 0));

        $stages = [];

        foreach ($groups as $group) {
            foreach ($group['stages'] ?? [] as $stage) {
                if (is_array($stage)) {
                    $stage['category'] = $group['id'] ?? 'build';
                    $stage['category_label'] = $group['label'] ?? str($group['id'] ?? 'build')->headline()->toString();
                    $stages[] = $stage;
                }
            }
        }

        return $stages;
    }

    /**
     * Groups the resolved stages for display, so completed groups can be collapsed.
     *
     * @param  array<int, array<string, mixed>>  $stageStates
     * @return array<int, array<string, mixed>>
     */
    private function groups(array $stageStates): array
    {
        $groups = [];

        foreach ($stageStates as $stage) {
            $id = (string) $stage['category'];

            $groups[$id] ??= [
                'id' => $id,
                'label' => (string) $stage['category_label'],
                'stages' => [],
            ];

            $groups[$id]['stages'][] = $stage;
        }

        return array_values(array_map(function (array $group): array {
            $states = array_column($group['stages'], 'state');
            $group['completed_count'] = count(array_filter($states, fn (string $state): bool => $state === 'complete'));
            $group['stage_count'] = count($states);

            if (in_array('current', $states, true)) {
                $group['state'] = 'current';
            } elseif ($group['completed_count'] === $group['stage_count']) {
                $group['state'] = 'complete';
            } elseif ($group['completed_count'] > 0) {
                $group['state'] = 'current';
            } else {
                $group['state'] = 'pending';
            }

            return $group;
        }, $groups));
    }

    /**
     * @return array<int, string>
     */
    private function seenStageIds(ImageBuild $build): array
    {
        $path = $build->log_path;

        if ($path === null || ! is_readable($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        preg_match_all('/\[image-builder:stage:([a-z0-9-]+)\]/', $contents, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private function formatEstimatedMinutes(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        if ($minutes < 60) {
            return $minutes.' minute'.($minutes === 1 ? '' : 's');
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        $duration = $hours.' hour'.($hours === 1 ? '' : 's');

        if ($remainingMinutes > 0) {
            $duration .= ' '.$remainingMinutes.' minute'.($remainingMinutes === 1 ? '' : 's');
        }

        return $duration;
    }
}
