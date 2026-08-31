<?php

namespace App\Services\Builds;

use App\Enums\BuildStatus;
use App\Models\ImageBuild;

class BuildProgress
{
    /**
     * @return array<string, mixed>
     */
    public function forBuild(ImageBuild $build): array
    {
        $template = $this->templateForTarget((string) $build->target);

        if ($template === null) {
            return ['available' => false];
        }

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
            'target' => $template['target'] ?? $build->target,
            'name' => $template['name'] ?? $build->target,
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

    /**
     * @return array<string, mixed>|null
     */
    private function templateForTarget(string $target): ?array
    {
        $path = rtrim(config('builds.image_builder_path'), '/').'/templates.json';

        if (! is_readable($path)) {
            return null;
        }

        $catalog = json_decode((string) file_get_contents($path), true);

        if (! is_array($catalog) || ! is_array($catalog['templates'] ?? null)) {
            return null;
        }

        foreach ($catalog['templates'] as $template) {
            if (is_array($template) && ($template['target'] ?? null) === $target) {
                $templateJson = $template['template_json_path'] ?? null;

                if (is_string($templateJson)) {
                    $templatePath = $this->metadataPath($templateJson);

                    if (is_readable($templatePath)) {
                        $fullTemplate = json_decode((string) file_get_contents($templatePath), true);

                        if (is_array($fullTemplate)) {
                            return $fullTemplate;
                        }
                    }
                }

                return $template;
            }
        }

        return null;
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

    private function metadataPath(string $templateJson): string
    {
        if (str_starts_with($templateJson, 'image-builder/')) {
            return rtrim(config('builds.image_builder_path'), '/').'/'.substr($templateJson, strlen('image-builder/'));
        }

        return dirname(base_path()).'/'.$templateJson;
    }
}
