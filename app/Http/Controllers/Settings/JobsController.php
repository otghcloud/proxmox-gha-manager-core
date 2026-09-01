<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobsController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function index(): View
    {
        return view('pages.settings.jobs', [
            'settings' => $this->settings->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'job_log_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $this->settings->set(SettingsRepository::JOB_LOG_RETENTION_DAYS, $validated['job_log_retention_days']);

        return redirect()
            ->route('settings.jobs.index')
            ->with('success', 'Job settings saved.');
    }
}
