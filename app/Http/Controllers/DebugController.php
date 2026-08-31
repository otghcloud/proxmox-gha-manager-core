<?php

namespace App\Http\Controllers;

use App\Enums\RunnerState;
use App\Models\ImageBuild;
use App\Models\Pool;
use App\Models\Runner;
use App\Models\RunnerEvent;
use App\Services\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class DebugController extends Controller
{
    /** Runners in these states are finished with and safe to purge from the history. */
    private const HISTORIC_STATES = [RunnerState::Destroyed, RunnerState::Failed];

    public function __construct(private readonly SettingsRepository $settings) {}

    public function index(): View
    {
        return view('pages.debug.index', [
            'reapingEnabled' => $this->settings->bool(SettingsRepository::REAPING_ENABLED),
            'autoSpawnEnabled' => $this->settings->bool(SettingsRepository::AUTO_SPAWN_ENABLED),
            'liveRunnerCount' => Runner::query()->whereNotIn('state', $this->historicStateValues())->count(),
            'historicRunnerCount' => Runner::query()->whereIn('state', $this->historicStateValues())->count(),
            'buildCount' => ImageBuild::query()->count(),
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

    /**
     * Queued rather than run inline, because destroying every VM can take minutes.
     */
    public function reapAll(): RedirectResponse
    {
        Artisan::queue('runners:reap', ['--all' => true]);

        return back()->with('success', 'Queued a forced reap of every managed VM.');
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

    /**
     * Export .env and SQLite database in a zip archive.
     */
    public function exportConfig(): BinaryFileResponse
    {
        $zipFilename = 'proxmox-gha-manager-export-'.date('YmdHis').'.zip';
        $tempZipPath = tempnam(sys_get_temp_dir(), 'cfg_export_').'.zip';

        $zip = new ZipArchive;
        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Failed to create configuration zip archive.');
        }

        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $zip->addFile($envPath, '.env');
        }

        $dbPath = config('database.connections.sqlite.database');
        if (is_string($dbPath) && file_exists($dbPath)) {
            $zip->addFile($dbPath, 'database.sqlite');
        }

        $zip->close();

        return response()->download($tempZipPath, $zipFilename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @return array<int, string>
     */
    private function historicStateValues(): array
    {
        return array_map(fn (RunnerState $state): string => $state->value, self::HISTORIC_STATES);
    }
}
