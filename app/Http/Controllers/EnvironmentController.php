<?php

namespace App\Http\Controllers;

use App\DataTables\EnvironmentsDataTable;
use App\Http\Requests\EnvironmentRequest;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\ProxmoxTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EnvironmentController extends Controller
{
    public function index(EnvironmentsDataTable $dataTable): mixed
    {
        return $dataTable->render('pages.environments.index');
    }

    public function create(): View
    {
        return view('pages.environments.create', [
            'environment' => new Environment,
            'accounts' => GitHubAccount::orderBy('login')->get(),
        ]);
    }

    public function store(EnvironmentRequest $request): RedirectResponse
    {
        $environment = Environment::create($request->validated());

        return redirect()
            ->route('environments.show', $environment)
            ->with('success', 'Environment created.');
    }

    public function show(Environment $environment): View
    {
        $environment->load(['pools.runnerTemplate', 'pools.proxmoxTargets', 'runnerTemplates.targetMappings', 'runnerTemplates.imageBuilds', 'githubAccount']);

        return view('pages.environments.show', [
            'environment' => $environment,
            'targets' => ProxmoxTarget::withCount('runnerTemplates')->orderBy('name')->get(),
        ]);
    }

    public function edit(Environment $environment): View
    {
        return view('pages.environments.edit', [
            'environment' => $environment,
            'accounts' => GitHubAccount::orderBy('login')->get(),
        ]);
    }

    public function update(EnvironmentRequest $request, Environment $environment): RedirectResponse
    {
        $data = $request->validated();

        $environment->update($data);

        return redirect()
            ->route('environments.show', $environment)
            ->with('success', 'Environment updated.');
    }

    public function destroy(Environment $environment): RedirectResponse
    {
        $environment->delete();

        return redirect()
            ->route('environments.index')
            ->with('success', 'Environment deleted.');
    }
}
