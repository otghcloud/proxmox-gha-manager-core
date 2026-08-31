<?php

namespace App\Support;

use App\Enums\RunnerState;
use App\Models\Runner;
use App\Models\RunnerEvent;
use App\Models\WorkflowJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The runner's life as a single ordered list, merging state changes with the milestones of the job
 * it served so the two do not have to be read side by side.
 */
class RunnerTimeline
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function for(Runner $runner, ?WorkflowJob $job = null): Collection
    {
        $entries = $runner->events()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (RunnerEvent $event): array => self::fromEvent($event));

        if ($job !== null) {
            $entries = $entries->concat(self::fromJob($job));
        }

        $entries = $entries
            ->sortBy([['at', 'asc'], ['order', 'asc']])
            ->values();

        $first = $entries->first()['at'] ?? null;
        $previous = null;

        return $entries->map(function (array $entry) use (&$previous, $first): array {
            $entry['since_previous'] = $previous === null ? null : (int) $previous->diffInSeconds($entry['at']);
            $entry['since_start'] = $first === null ? null : (int) $first->diffInSeconds($entry['at']);
            $previous = $entry['at'];

            return $entry;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function fromEvent(RunnerEvent $event): array
    {
        $state = RunnerState::tryFrom((string) $event->to_state);

        return [
            'at' => $event->created_at,
            'order' => 0,
            'title' => $state?->label() ?? ucfirst((string) $event->to_state),
            'from' => $event->from_state,
            'detail' => $event->reason,
            'colour' => $state?->colour() ?? 'secondary',
            'icon' => self::stateIcon($state),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fromJob(WorkflowJob $job): array
    {
        $entries = [];

        if ($job->started_at !== null) {
            $entries[] = [
                'at' => $job->started_at,
                // Sorted after the matching state change when both land on the same second.
                'order' => 1,
                'title' => 'Job started',
                'from' => null,
                'detail' => $job->job_name.' · '.$job->repository_full_name,
                'colour' => 'blue',
                'icon' => 'fa-solid fa-play',
            ];
        }

        if ($job->completed_at !== null) {
            $entries[] = [
                'at' => $job->completed_at,
                'order' => -1,
                'title' => 'Job '.($job->conclusion?->label() ? strtolower($job->conclusion->label()) : 'finished'),
                'from' => null,
                'detail' => $job->durationSeconds() === null ? null : 'ran for '.$job->durationSeconds().'s',
                'colour' => $job->conclusion?->colour() ?? 'secondary',
                'icon' => $job->conclusion?->icon() ?? 'fa-solid fa-flag-checkered',
            ];
        }

        return $entries;
    }

    private static function stateIcon(?RunnerState $state): string
    {
        return match ($state) {
            RunnerState::Spawning => 'fa-solid fa-wand-magic-sparkles',
            RunnerState::Idle => 'fa-solid fa-circle-check',
            RunnerState::Busy => 'fa-solid fa-gears',
            RunnerState::Reaping => 'fa-solid fa-broom',
            RunnerState::Failed => 'fa-solid fa-triangle-exclamation',
            RunnerState::Destroyed => 'fa-solid fa-trash',
            default => 'fa-solid fa-circle',
        };
    }

    public static function lifetimeSeconds(Runner $runner): ?int
    {
        $end = $runner->destroyed_at ?? Carbon::now();

        return (int) $runner->created_at->diffInSeconds($end);
    }
}
