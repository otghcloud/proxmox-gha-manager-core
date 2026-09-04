<?php

namespace App\Http\Controllers\Settings;

use App\Enums\PoolOs;
use App\Http\Controllers\Controller;
use App\Http\Requests\CredentialRequest;
use App\Models\Credential;
use App\Services\Credentials\DefaultCredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CredentialController extends Controller
{
    public function index(): View
    {
        return view('pages.settings.templates-credentials', [
            'credentials' => Credential::query()->orderBy('os')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('pages.settings.templates-credentials-form', [
            'credential' => new Credential(['os' => PoolOs::Linux]),
            'oses' => PoolOs::cases(),
        ]);
    }

    public function store(CredentialRequest $request): RedirectResponse
    {
        Credential::create($this->cleanSecrets($request->validated()));

        return redirect()->route('settings.templates.credentials.index')->with('success', 'Credential created.');
    }

    public function edit(Credential $credential): View
    {
        return view('pages.settings.templates-credentials-form', ['credential' => $credential, 'oses' => PoolOs::cases()]);
    }

    public function update(CredentialRequest $request, Credential $credential): RedirectResponse
    {
        $credential->update($this->cleanSecrets($request->validated()));

        return redirect()->route('settings.templates.credentials.index')->with('success', 'Credential updated.');
    }

    public function destroy(Credential $credential): RedirectResponse
    {
        if ($credential->runnerTemplates()->exists()) {
            return back()->with('error', 'Reassign this credential from its templates before deleting it.');
        }

        $credential->delete();

        return redirect()->route('settings.templates.credentials.index')->with('success', 'Credential deleted.');
    }

    public function ensureDefault(DefaultCredentialService $defaults): RedirectResponse
    {
        $defaults->ensureLinuxCredential();

        return back()->with('success', 'The default Linux SSH credential is ready.');
    }

    /** @param array<string, mixed> $data */
    private function cleanSecrets(array $data): array
    {
        foreach (['password', 'private_key', 'public_key'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        return $data;
    }
}
