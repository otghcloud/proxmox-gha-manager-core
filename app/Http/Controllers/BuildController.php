<?php

namespace App\Http\Controllers;

use App\DataTables\BuildsDataTable;
use App\Models\ImageBuild;
use App\Services\Builds\BuildCanceller;
use App\Services\Builds\BuildProgress;
use App\Services\Builds\TemplateCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuildController extends Controller
{
    private const MAX_CHUNK_BYTES = 262144;

    public function __construct(
        private readonly BuildProgress $progress,
        private readonly TemplateCatalog $catalog,
    ) {}

    public function index(BuildsDataTable $dataTable): mixed
    {
        return $dataTable->render('pages.builds.index');
    }

    public function show(ImageBuild $imageBuild): View
    {
        $imageBuild->load(['environment', 'runnerTemplate', 'proxmoxTarget', 'triggeredBy']);

        return view('pages.builds.show', [
            'build' => $imageBuild,
            'catalogEntry' => $this->catalog->entryForId($imageBuild->template_catalog_id, $imageBuild->builder_type),
            'log' => $this->readLog($imageBuild),
            'progress' => $this->progress->forBuild($imageBuild),
        ]);
    }

    /**
     * Incremental log tail, so a running build can be followed without re-sending the whole file.
     */
    public function log(ImageBuild $imageBuild, Request $request): JsonResponse
    {
        $offset = max(0, $request->integer('offset'));
        $path = $imageBuild->log_path;

        if ($path === null || ! is_readable($path)) {
            return response()->json(['content' => '', 'offset' => 0, 'finished' => $imageBuild->status->isFinished()]);
        }

        $size = filesize($path);

        if ($offset > $size) {
            $offset = 0;
        }

        $handle = fopen($path, 'r');
        fseek($handle, $offset);
        // Capped so a long-running build cannot return megabytes in a single poll.
        $content = (string) fread($handle, self::MAX_CHUNK_BYTES);
        fclose($handle);

        return response()->json([
            'content' => $content,
            'offset' => $offset + strlen($content),
            'status' => $imageBuild->status->value,
            'finished' => $imageBuild->status->isFinished() && ($offset + strlen($content)) >= $size,
            'progress' => $this->progress->forBuild($imageBuild->fresh()),
        ]);
    }

    public function cancel(ImageBuild $imageBuild, BuildCanceller $canceller): RedirectResponse
    {
        if ($imageBuild->status->isFinished()) {
            return back()->with('error', 'That build has already finished.');
        }

        $canceller->cancel($imageBuild, 'force killed from the web interface');

        return back()->with('success', 'Build force killed.');
    }

    public function destroy(ImageBuild $imageBuild): RedirectResponse
    {
        if (! $imageBuild->status->isFinished()) {
            return back()->with('error', 'Only finished builds can be deleted.');
        }

        $logPath = $imageBuild->log_path;

        $imageBuild->delete();

        if ($logPath !== null && is_file($logPath)) {
            @unlink($logPath);
        }

        return redirect()
            ->route('builds.index')
            ->with('success', 'Build deleted.');
    }

    private function readLog(ImageBuild $build): ?string
    {
        if ($build->log_path && is_readable($build->log_path)) {
            return (string) file_get_contents($build->log_path);
        }

        return $build->storedLog()?->body;
    }
}
