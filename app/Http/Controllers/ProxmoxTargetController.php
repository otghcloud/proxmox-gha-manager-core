<?php

namespace App\Http\Controllers;

use App\DataTables\ProxmoxTargetsDataTable;
use App\Http\Requests\ProxmoxTargetRequest;
use App\Models\Environment;
use App\Models\ProxmoxTarget;
use App\Services\Proxmox\ProxmoxClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ProxmoxTargetController extends Controller
{
    public function index(ProxmoxTargetsDataTable $dataTable): mixed
    {
        return $dataTable->render('pages.nodes.index');
    }

    public function show(ProxmoxTarget $target): View
    {
        $target->load(['runnerTemplates', 'runners']);

        return view('pages.nodes.show', compact('target'));
    }

    public function standaloneCreate(): View
    {
        return view('pages.nodes.create', [
            'environment' => null,
            'target' => new ProxmoxTarget(['max_total_vms' => 12, 'enabled' => true, 'template_vmid_range_start' => 801, 'template_vmid_range_end' => 899, 'runner_vmid_range_start' => 901, 'runner_vmid_range_end' => 999]),
        ]);
    }

    public function standaloneStore(ProxmoxTargetRequest $request): RedirectResponse
    {
        $target = ProxmoxTarget::create($this->fillAuthDefaults($request->validated()));

        return redirect()->route('nodes.index')->with('success', "Proxmox target {$target->name} created.");
    }

    public function storageOptions(Request $request): JsonResponse
    {
        $isPasswordAuth = $request->input('proxmox_auth_realm') === ProxmoxTarget::AUTH_REALM_PASSWORD;

        $data = $request->validate([
            'target_id' => ['nullable', 'integer', 'exists:proxmox_targets,id'],
            'proxmox_url' => ['required', 'url', 'max:255'],
            'proxmox_node' => ['required', 'string', 'max:255'],
            'proxmox_auth_realm' => ['nullable', Rule::in([ProxmoxTarget::AUTH_REALM_API_TOKEN, ProxmoxTarget::AUTH_REALM_PASSWORD])],
            'proxmox_token_id' => [$isPasswordAuth ? 'nullable' : 'required', 'string', 'max:255'],
            'proxmox_token_secret' => ['nullable', 'string'],
            'proxmox_username' => [$isPasswordAuth ? 'required' : 'nullable', 'string', 'max:255'],
            'proxmox_password' => ['nullable', 'string'],
            'proxmox_verify_tls' => ['boolean'],
            'proxmox_ca_bundle' => ['nullable', 'string', 'max:255'],
            'proxmox_resource_pool' => ['nullable', 'string', 'max:255'],
        ]);

        $data['proxmox_auth_realm'] = $isPasswordAuth ? ProxmoxTarget::AUTH_REALM_PASSWORD : ProxmoxTarget::AUTH_REALM_API_TOKEN;

        $existing = ! empty($data['target_id']) ? ProxmoxTarget::find($data['target_id']) : null;
        $secretField = $isPasswordAuth ? 'proxmox_password' : 'proxmox_token_secret';

        if (blank($data[$secretField] ?? null) && $existing !== null) {
            $data[$secretField] = $existing->{$secretField};
        }

        if (blank($data[$secretField] ?? null)) {
            $label = $isPasswordAuth ? 'Password' : 'API token secret';

            return response()->json(['message' => "{$label} is required."], 422);
        }

        try {
            $target = new ProxmoxTarget($data);
            $proxmox = new ProxmoxClient($target);

            return response()->json([
                'iso' => $this->storageNames($proxmox->storages('iso')),
                'images' => $this->storageNames($proxmox->storages('images')),
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function isos(ProxmoxTarget $target): JsonResponse
    {
        try {
            return response()->json([
                'images' => (new ProxmoxClient($target))->isoImages(),
            ]);
        } catch (Throwable $e) {
            return response()->json(['images' => [], 'error' => $e->getMessage()], 502);
        }
    }

    public function standaloneEdit(ProxmoxTarget $target): View
    {
        return view('pages.nodes.edit', ['environment' => null, 'target' => $target]);
    }

    public function standaloneUpdate(ProxmoxTargetRequest $request, ProxmoxTarget $target): RedirectResponse
    {
        $data = $request->validated();
        $data['drained_at'] = ($data['drained'] ?? false) ? now() : null;
        unset($data['drained']);
        if (blank($data['proxmox_token_secret'] ?? null)) {
            unset($data['proxmox_token_secret']);
        }
        if (blank($data['proxmox_password'] ?? null)) {
            unset($data['proxmox_password']);
        }
        $target->update($data);

        return redirect()->route('nodes.index')->with('success', 'Proxmox target updated.');
    }

    /**
     * `proxmox_token_id`/`proxmox_token_secret` are non-nullable columns; when creating a target
     * with the `password` realm they're simply unused, so default them to an empty string rather
     * than omitting them from the insert.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillAuthDefaults(array $data): array
    {
        $data['proxmox_token_id'] ??= '';
        $data['proxmox_token_secret'] ??= '';

        return $data;
    }

    public function standaloneDestroy(ProxmoxTarget $target): RedirectResponse
    {
        $target->delete();

        return redirect()->route('nodes.index')->with('success', 'Proxmox target deleted.');
    }

    public function test(ProxmoxTarget $target): RedirectResponse
    {
        try {
            $client = new ProxmoxClient($target);
            $vms = $client->clusterVms();
            $targetVms = $client->filterTargetVms($vms, $target);
        } catch (Throwable $e) {
            return back()->with('error', 'Proxmox connection failed: '.$e->getMessage());
        }

        return back()->with('success', "Proxmox node {$target->name} is reachable (".count($targetVms).' VMs visible).');
    }

    public function create(Environment $environment): View
    {
        return view('pages.nodes.create', [
            'environment' => $environment,
            'target' => new ProxmoxTarget(['max_total_vms' => 12, 'enabled' => true, 'template_vmid_range_start' => 801, 'template_vmid_range_end' => 899, 'runner_vmid_range_start' => 901, 'runner_vmid_range_end' => 999]),
        ]);
    }

    public function store(ProxmoxTargetRequest $request, Environment $environment): RedirectResponse
    {
        $target = ProxmoxTarget::create($request->validated());

        return redirect()
            ->route('environments.show', $environment)
            ->with('success', "Proxmox target {$target->name} created.");
    }

    public function edit(Environment $environment, ProxmoxTarget $target): View
    {
        return view('pages.nodes.edit', compact('environment', 'target'));
    }

    public function update(ProxmoxTargetRequest $request, Environment $environment, ProxmoxTarget $target): RedirectResponse
    {
        $data = $request->validated();
        if (blank($data['proxmox_token_secret'] ?? null)) {
            unset($data['proxmox_token_secret']);
        }

        $target->update($data);

        return redirect()
            ->route('environments.show', $environment)
            ->with('success', 'Proxmox target updated.');
    }

    public function destroy(Environment $environment, ProxmoxTarget $target): RedirectResponse
    {
        $target->delete();

        return redirect()
            ->route('environments.show', $environment)
            ->with('success', 'Proxmox target deleted.');
    }

    private function storageNames(array $storages): array
    {
        return collect($storages)
            ->filter(fn (array $storage): bool => isset($storage['storage']) && ($storage['enabled'] ?? 1) == 1)
            ->map(fn (array $storage): array => [
                'name' => (string) $storage['storage'],
                'type' => $storage['type'] ?? null,
                'available' => isset($storage['avail']) ? (int) $storage['avail'] : null,
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }
}
