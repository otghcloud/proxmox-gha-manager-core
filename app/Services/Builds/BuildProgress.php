<?php

namespace App\Services\Builds;

use App\Enums\BuildStatus;
use App\Models\ImageBuild;
use App\Services\Builds\Packer\TemplateCatalog;

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

        $template = $entry->data();

        $stages = $template['build_stages'] ?? [];

        if (! is_array($stages) || $stages === []) {
            return ['available' => false];
        }

        $seen = $this->seenStageIds($build);
        $lastSeen = $seen === [] ? null : end($seen);
        $finished = $build->status->isFinished();
        $succeeded = $build->status === BuildStatus::Succeeded;
        $stageCount = count($stages);
        $seenCount = count(array_intersect(array_column($stages, 'id'), $seen));
        $currentStage = null;
        $estimatedMinutes = $this->estimatedMinutes($template);

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
                'state' => $state,
            ];
        }, $stages);

        $percent = $succeeded
            ? 100
            : ($stageCount > 0 ? (int) floor(($seenCount / $stageCount) * 100) : 0);

        return [
            'available' => true,
            'target' => $entry->target(),
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
        ];
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

    private function estimatedMinutes(array $template): ?int
    {
        $minutes = $template['build_requirements']['estimated_minutes'] ?? null;

        return is_numeric($minutes) && (int) $minutes > 0 ? (int) $minutes : null;
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
