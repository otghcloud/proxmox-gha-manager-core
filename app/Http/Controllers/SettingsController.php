<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SettingsRepository;
use App\Services\Templates\TemplateUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function index(): View
    {
        return view('pages.settings.index', [
            'settings' => $this->settings->all(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_url' => ['required', 'url'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'template_retention_mode' => ['required', Rule::in([SettingsRepository::RETENTION_AUTO, SettingsRepository::RETENTION_KEEP_LAST_N])],
            'template_retention_generations' => ['required_if:template_retention_mode,'.SettingsRepository::RETENTION_KEEP_LAST_N, 'integer', 'min:1', 'max:20'],
            'job_log_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
            'template_auto_check_enabled' => ['nullable', 'boolean'],
            'template_check_interval_hours' => ['required_if:template_auto_check_enabled,1', 'nullable', 'integer', 'min:1', 'max:168'],
        ]);

        $this->settings->setMany([
            'app_url' => rtrim($validated['app_url'], '/'),
            'timezone' => $validated['timezone'],
            SettingsRepository::TEMPLATE_RETENTION_MODE => $validated['template_retention_mode'],
            SettingsRepository::TEMPLATE_RETENTION_GENERATIONS => $validated['template_retention_generations'] ?? 1,
            SettingsRepository::JOB_LOG_RETENTION_DAYS => $validated['job_log_retention_days'],
            SettingsRepository::TEMPLATE_AUTO_CHECK_ENABLED => $request->has('template_auto_check_enabled') ? '1' : '0',
            SettingsRepository::TEMPLATE_CHECK_INTERVAL_HOURS => $validated['template_check_interval_hours'] ?? 24,
        ]);

        return redirect()
            ->route('settings.index')
            ->with('success', 'Settings saved.');
    }

    public function checkTemplateUpdates(TemplateUpdateService $service): RedirectResponse
    {
        $result = $service->checkForUpdates();
        $count = count($result['updates'] ?? []);

        $message = $count > 0
            ? "Template update check complete. {$count} template update(s) available."
            : 'Template update check complete. All templates are up to date.';

        return redirect()
            ->route('settings.index')
            ->with('success', $message);
    }
}
