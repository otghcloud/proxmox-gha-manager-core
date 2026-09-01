<?php

namespace App\Http\Controllers\Settings;

use App\Enums\RunnerState;
use App\Http\Controllers\Controller;
use App\Models\ImageBuild;
use App\Models\Pool;
use App\Models\Runner;
use App\Models\RunnerEvent;
use App\Models\WebhookDelivery;
use App\Models\WorkflowJob;
use App\Services\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DebugController extends Controller
{
    /** Runners in these states are finished with and safe to purge from the history. */
    private const HISTORIC_STATES = [RunnerState::Destroyed, RunnerState::Failed];

    public function __construct(private readonly SettingsRepository $settings) {}

    public function index(): View
    {
        return view('pages.settings.debug', [
            'reapingEnabled' => $this->settings->bool(SettingsRepository::REAPING_ENABLED),
            'autoSpawnEnabled' => $this->settings->bool(SettingsRepository::AUTO_SPAWN_ENABLED),
            'liveRunnerCount' => Runner::query()->whereNotIn('state', $this->historicStateValues())->count(),
            'historicRunnerCount' => Runner::query()->whereIn('state', $this->historicStateValues())->count(),
            'buildCount' => ImageBuild::query()->count(),
            'webhookDeliveryCount' => WebhookDelivery::query()->count(),
            'workflowJobCount' => WorkflowJob::query()->count(),
            'pools' => Pool::with(['environment', 'proxmoxTargets'])->orderBy('name')->get(),
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'in:'.SettingsRepository::REAPING_ENABLED.','.SettingsRepository::AUTO_SPAWN_ENABLED],
            'enabled' => ['required', 'boolean'],
        ]);

        $this->settings->set($validated['key'], $request->boolean('enabled') ? '1' : '0');

        $label = $validated['key'] === SettingsRepository::REAPING_ENABLED ? 'Reaping' : 'Auto spawning';

        return back()->with('success', "{$label} ".($request->boolean('enabled') ? 'enabled.' : 'disabled.'));
    }

    public function reapAll(): RedirectResponse
    {
        $this->settings->set(SettingsRepository::FORCE_REAP_ALL_REQUESTED, '1');

        return back()->with('success', 'The next scheduled reaper pass will force-reap every managed VM.');
    }

    public function clearRunnerHistory(): RedirectResponse
    {
        $deleted = DB::transaction(function (): int {
            $ids = Runner::query()->whereIn('state', $this->historicStateValues())->pluck('id');

            RunnerEvent::query()->whereIn('runner_id', $ids)->delete();

            return Runner::query()->whereIn('id', $ids)->delete();
        });

        return back()->with('success', "Deleted {$deleted} historic runner record(s).");
    }

    /**
     * Removes every build, including ones stuck in a running state.
     */
    public function clearBuildHistory(): RedirectResponse
    {
        $logPaths = ImageBuild::query()->whereNotNull('log_path')->pluck('log_path');

        $deleted = ImageBuild::query()->delete();

        foreach ($logPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return back()->with('success', "Deleted {$deleted} build record(s).");
    }

    public function purgeWebhookLogs(): RedirectResponse
    {
        $deleted = WebhookDelivery::query()->delete();

        return back()->with('success', "Deleted {$deleted} webhook delivery log(s).");
    }

    /**
     * Removes every workflow job record, including their stored logs. Runners keep their history.
     */
    public function purgeWorkflowJobs(): RedirectResponse
    {
        $logPaths = WorkflowJob::query()->whereNotNull('log_path')->pluck('log_path');

        $deleted = WorkflowJob::query()->delete();

        foreach ($logPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return back()->with('success', "Deleted {$deleted} GitHub job record(s).");
    }

    /**
     * Export .env and SQLite database in a zip archive.
     */
    public function exportConfig(): StreamedResponse
    {
        $zipFilename = 'proxmox-gha-manager-export-'.date('YmdHis').'.zip';

        return response()->streamDownload(function (): void {
            $tempZipPath = tempnam(sys_get_temp_dir(), 'cfg_export_').'.zip';

            $zip = new ZipArchive;
            if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $envPath = base_path('.env');
                if (file_exists($envPath)) {
                    $zip->addFile($envPath, '.env');
                }

                $dbPath = config('database.connections.sqlite.database');
                if (is_string($dbPath) && file_exists($dbPath)) {
                    $zip->addFile($dbPath, 'database.sqlite');
                }

                $zip->close();
            }

            if (file_exists($tempZipPath)) {
                readfile($tempZipPath);
                @unlink($tempZipPath);
            }
        }, $zipFilename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function historicStateValues(): array
    {
        return array_map(fn (RunnerState $state): string => $state->value, self::HISTORIC_STATES);
    }
}
