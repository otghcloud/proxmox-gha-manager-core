<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Models\Runner;
use App\Models\WorkflowJob;
use App\Services\Builds\Packer\TemplateCatalog;
use App\Services\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GeneralController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function overview(TemplateCatalog $catalog): View
    {
        return view('pages.settings.overview', [
            'settings' => $this->settings->all(),
            'templatesVersion' => $catalog->imageBuilderVersion(),
            'nodeCount' => ProxmoxTarget::count(),
            'poolCount' => Pool::count(),
            'runnerCount' => Runner::count(),
            'jobCount' => WorkflowJob::count(),
        ]);
    }

    public function application(): View
    {
        return view('pages.settings.application', [
            'settings' => $this->settings->all(),
        ]);
    }

    public function updateApplication(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_url' => ['required', 'url'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
        ]);

        $this->settings->setMany([
            'app_url' => rtrim($validated['app_url'], '/'),
            'timezone' => $validated['timezone'],
        ]);

        return redirect()
            ->route('settings.application')
            ->with('success', 'Application settings saved.');
    }
}
