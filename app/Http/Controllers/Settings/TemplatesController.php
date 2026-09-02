<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\SettingsRepository;
use App\Services\Templates\TemplateDownloadService;
use App\Services\Templates\TemplateUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class TemplatesController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function index(TemplateDownloadService $downloader): View
    {
        return view('pages.settings.templates', [
            'settings' => $this->settings->all(),
            'installedVersions' => $downloader->installedVersions(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'template_retention_mode' => ['required', Rule::in([SettingsRepository::RETENTION_AUTO, SettingsRepository::RETENTION_KEEP_LAST_N])],
            'template_retention_generations' => ['required_if:template_retention_mode,'.SettingsRepository::RETENTION_KEEP_LAST_N, 'integer', 'min:1', 'max:20'],
            'template_auto_check_enabled' => ['nullable', 'boolean'],
            'template_check_interval_hours' => ['required_if:template_auto_check_enabled,1', 'nullable', 'integer', 'min:1', 'max:168'],
            'template_auto_download_enabled' => ['nullable', 'boolean'],
            'template_auto_build_enabled' => ['nullable', 'boolean'],
            'template_bundle_retention_mode' => ['required', Rule::in([SettingsRepository::RETENTION_AUTO, SettingsRepository::RETENTION_KEEP_LAST_N])],
            'template_bundle_retention_generations' => ['required_if:template_bundle_retention_mode,'.SettingsRepository::RETENTION_KEEP_LAST_N, 'integer', 'min:1', 'max:20'],
        ]);

        $this->settings->setMany([
            SettingsRepository::TEMPLATE_RETENTION_MODE => $validated['template_retention_mode'],
            SettingsRepository::TEMPLATE_RETENTION_GENERATIONS => $validated['template_retention_generations'] ?? 1,
            SettingsRepository::TEMPLATE_AUTO_CHECK_ENABLED => $request->has('template_auto_check_enabled') ? '1' : '0',
            SettingsRepository::TEMPLATE_CHECK_INTERVAL_HOURS => $validated['template_check_interval_hours'] ?? 24,
            SettingsRepository::TEMPLATE_AUTO_DOWNLOAD_ENABLED => $request->has('template_auto_download_enabled') ? '1' : '0',
            SettingsRepository::TEMPLATE_AUTO_BUILD_ENABLED => $request->has('template_auto_build_enabled') ? '1' : '0',
            SettingsRepository::TEMPLATE_BUNDLE_RETENTION_MODE => $validated['template_bundle_retention_mode'],
            SettingsRepository::TEMPLATE_BUNDLE_RETENTION_GENERATIONS => $validated['template_bundle_retention_generations'] ?? 1,
        ]);

        return redirect()
            ->route('settings.templates.index')
            ->with('success', 'Template settings saved.');
    }

    public function checkUpdates(TemplateUpdateService $service): RedirectResponse
    {
        $result = $service->checkForUpdates();
        $count = count($result['updates'] ?? []);

        $message = $count > 0
            ? "Template update check complete. {$count} template update(s) available."
            : 'Template update check complete. All templates are up to date.';

        return redirect()
            ->route('settings.templates.index')
            ->with('success', $message);
    }

    public function downloadUpdate(TemplateDownloadService $downloader): RedirectResponse
    {
        try {
            $result = $downloader->download();

            return redirect()
                ->route('settings.templates.index')
                ->with('success', "Downloaded and activated template bundle v{$result['version']}.");
        } catch (Throwable $e) {
            return redirect()
                ->route('settings.templates.index')
                ->with('error', 'Failed to download template update: '.$e->getMessage());
        }
    }

    public function activateVersion(string $version, TemplateDownloadService $downloader): RedirectResponse
    {
        try {
            $downloader->activate($version);

            return redirect()
                ->route('settings.templates.index')
                ->with('success', "Activated template bundle v{$version}.");
        } catch (Throwable $e) {
            return redirect()
                ->route('settings.templates.index')
                ->with('error', 'Failed to activate template bundle: '.$e->getMessage());
        }
    }
}
