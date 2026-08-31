<?php

namespace App\Http\Controllers;

use App\DataTables\RunnersDataTable;
use App\Enums\RunnerState;
use App\Models\Environment;
use App\Models\Runner;
use App\Services\Provisioning\EnvironmentServices;
use App\Support\RunnerTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class RunnerController extends Controller
{
    public function index(RunnersDataTable $dataTable): mixed
    {
        return $dataTable->render('pages.runners.index', [
            'environments' => Environment::orderBy('name')->get(),
        ]);
    }

    public function show(Runner $runner): View
    {
        $runner->load(['environment', 'pool.runnerTemplate', 'proxmoxTarget', 'servedJob']);
        $job = $runner->servedJob;

        return view('pages.runners.show', [
            'runner' => $runner,
            'job' => $job,
            'timeline' => RunnerTimeline::for($runner, $job)->reverse(),
            'lifetimeSeconds' => RunnerTimeline::lifetimeSeconds($runner),
        ]);
    }

    public function destroy(Runner $runner, EnvironmentServices $services): RedirectResponse
    {
        if ($runner->state === RunnerState::Destroyed) {
            return back()->with('error', 'That runner has already been destroyed.');
        }

        try {
            $services->provisioner($runner->environment)->destroy($runner, 'destroyed from the web interface');
        } catch (Throwable $e) {
            return back()->with('error', 'Could not destroy the runner: '.$e->getMessage());
        }

        return redirect()
            ->route('runners.index')
            ->with('success', "Destroyed VM {$runner->vmid}.");
    }
}
