<?php

namespace App\Http\Controllers;

use App\DataTables\WorkflowJobsDataTable;
use App\Models\Environment;
use App\Models\WorkflowJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkflowJobController extends Controller
{
    public function index(WorkflowJobsDataTable $dataTable): mixed
    {
        return $dataTable->render('pages.jobs.index', [
            'environments' => Environment::orderBy('name')->get(),
        ]);
    }

    public function show(WorkflowJob $job): View
    {
        $job->load(['environment', 'runner.pool', 'runner.proxmoxTarget']);

        return view('pages.jobs.show', ['job' => $job]);
    }

    public function log(WorkflowJob $job): Response|StreamedResponse|BinaryFileResponse
    {
        if (! $job->hasLog()) {
            abort(404, 'Job log file is missing or not readable.');
        }

        $realPath = realpath($job->log_path);

        if ($realPath === false || ! is_readable($realPath)) {
            abort(404, 'Job log file is not readable.');
        }

        return response()->file($realPath, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="job-'.$job->github_job_id.'.log"',
        ]);
    }

    public function destroy(WorkflowJob $job): RedirectResponse
    {
        $job->delete();

        return redirect()->route('jobs.index')
            ->with('success', "Job {$job->job_name} has been deleted.");
    }
}
