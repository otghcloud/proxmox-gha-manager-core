<?php

namespace App\Http\Controllers;

use App\DataTables\RunnerTemplatesDataTable;
use App\Enums\BuildStatus;
use App\Enums\PoolOs;
use App\Enums\RunnerState;
use App\Http\Requests\RunnerTemplateBuildRequest;
use App\Http\Requests\RunnerTemplateRequest;
use App\Models\Environment;
use App\Models\ImageBuild;
use App\Models\ProxmoxTarget;
use App\Models\RetiredTemplateVmid;
use App\Models\Runner;
use App\Models\RunnerTemplate;
use App\Services\Builds\ImageBuilder;
use App\Services\Builds\Packer\TemplateCatalog;
use App\Services\Builds\TemplateRebuilder;
use App\Services\Proxmox\ProxmoxClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class RunnerTemplateController extends Controller
{
    public function __construct(
        private readonly TemplateRebuilder $rebuilder,
        private readonly TemplateCatalog $catalog,
    ) {}

    public function index(RunnerTemplatesDataTable $dataTable): mixed
    {
        return $dataTable->render('pages.templates.index');
    }

    public function create(): View
    {
        return view('pages.templates.create', $this->formData(new RunnerTemplate(['os' => PoolOs::Linux])));
    }

    public function store(RunnerTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $targetIds = $data['target_ids'] ?? [];
        $mappings = $data['mappings'] ?? [];
        unset($data['target_ids'], $data['mappings']);

        $template = RunnerTemplate::create($data);
        $this->syncMappings($template, $targetIds, $mappings);

        return redirect()
            ->route('templates.show', $template)
            ->with('success', "Template {$template->name} created.");
    }

    public function show(RunnerTemplate $runnerTemplate): View
    {
        $runnerTemplate->load(['environment', 'pools.proxmoxTargets', 'imageBuilds.proxmoxTarget', 'targetMappings']);
        $buildingTargetIds = $runnerTemplate->imageBuilds()
            ->whereIn('status', [BuildStatus::Queued->value, BuildStatus::Running->value])
            ->pluck('proxmox_target_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();

        $retiredVmids = RetiredTemplateVmid::with('proxmoxTarget')
            ->where('runner_template_id', $runnerTemplate->id)
            ->whereNull('deleted_at')
            ->orderByDesc('retired_at')
            ->get();

        return view('pages.templates.show', [
            'template' => $runnerTemplate,
            'catalogEntry' => $this->catalog->entryForId($runnerTemplate->template_catalog_id),
            'targets' => $runnerTemplate->targetMappings,
            'buildingTargetIds' => $buildingTargetIds,
            'buildableTargets' => $runnerTemplate->buildableTargets(),
            'retiredVmids' => $retiredVmids,
            'retiredUsage' => $retiredVmids->mapWithKeys(fn (RetiredTemplateVmid $retired): array => [
                $retired->id => Runner::where('proxmox_target_id', $retired->proxmox_target_id)
                    ->where('source_template_vmid', $retired->vmid)
                    ->whereNot('state', RunnerState::Destroyed->value)
                    ->count(),
            ])->all(),
        ]);
    }

    public function edit(RunnerTemplate $runnerTemplate): View
    {
        return view('pages.templates.edit', $this->formData($runnerTemplate));
    }

    public function update(RunnerTemplateRequest $request, RunnerTemplate $runnerTemplate): RedirectResponse
    {
        $data = $request->validated();
        $targetIds = $data['target_ids'] ?? [];
        $mappings = $data['mappings'] ?? [];
        unset($data['target_ids'], $data['mappings']);

        $runnerTemplate->update($data);
        $this->syncMappings($runnerTemplate, $targetIds, $mappings);

        return redirect()
            ->route('templates.show', $runnerTemplate)
            ->with('success', 'Template updated.');
    }

    private function syncMappings(RunnerTemplate $template, array $targetIds, array $mappings): void
    {
        DB::transaction(function () use ($template, $targetIds, $mappings): void {
            $pivot = [];
            foreach ($targetIds as $targetId) {
                $pivot[$targetId] = $mappings[$targetId] ?? [];
            }
            $template->targetMappings()->sync($pivot);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(RunnerTemplate $template): array
    {
        return [
            'template' => $template,
            'environments' => Environment::orderBy('name')->get(),
            'targets' => ProxmoxTarget::orderBy('name')->get(),
            'catalogTemplates' => $this->catalog->templates(),
        ];
    }

    public function destroy(RunnerTemplate $runnerTemplate): RedirectResponse
    {
        $runnerTemplate->delete();

        return redirect()
            ->route('templates.index')
            ->with('success', 'Template deleted.');
    }

    public function build(RunnerTemplateBuildRequest $request, RunnerTemplate $runnerTemplate, ?ProxmoxTarget $target = null): RedirectResponse
    {
        $targets = $target !== null
            ? $runnerTemplate->targetMappings()->whereKey($target->id)->get()
            : $runnerTemplate->targetMappings()->whereIn('proxmox_targets.id', $request->targetIds())->get();

        foreach ($targets as $node) {
            if ($node->pivot->build_iso_file !== null || ! is_string($node->pivot->build_iso_url) || $node->pivot->build_iso_url === '') {
                continue;
            }

            if ($node->build_iso_storage === null) {
                return back()->with('error', "Set the build ISO storage on {$node->name} before downloading its installation ISO.");
            }

            try {
                $isoFile = (new ProxmoxClient($node))->downloadIso($node->build_iso_storage, $node->pivot->build_iso_url);
                $runnerTemplate->targetMappings()->updateExistingPivot($node->id, ['build_iso_file' => $isoFile]);
                $node->pivot->build_iso_file = $isoFile;
            } catch (Throwable $e) {
                return back()->with('error', "Could not download the installation ISO for {$node->name}: {$e->getMessage()}");
            }
        }

        $targets = $targets->filter(fn (ProxmoxTarget $node): bool => $node->pivot->build_iso_file !== null);

        $catalogEntry = $this->catalog->entryForId($runnerTemplate->template_catalog_id);

        if ($targets->isEmpty() || $catalogEntry === null) {
            return back()->with('error', 'Configure a build target and an installation ISO for at least one node before building.');
        }

        if (! $catalogEntry->isBuildEnabled()) {
            return back()->with('error', $catalogEntry->disabledReason() ?? 'The selected template is not buildable.');
        }

        if (! ImageBuilder::isAvailable()) {
            return back()->with('error', 'The image builder templates are not present in this installation.');
        }

        $misconfigured = $targets->first(fn (ProxmoxTarget $node): bool => $node->build_iso_storage === null || $node->build_vm_storage === null);

        if ($misconfigured !== null) {
            return back()->with('error', "Set the build ISO and VM storage on {$misconfigured->name} before building.");
        }

        $running = ImageBuild::where('runner_template_id', $runnerTemplate->id)
            ->whereIn('proxmox_target_id', $targets->pluck('id'))
            ->whereIn('status', [BuildStatus::Queued->value, BuildStatus::Running->value])
            ->exists();

        if ($running) {
            return back()->with('error', 'A build for this template is already in progress.');
        }

        try {
            $builds = $this->rebuilder->queue($runnerTemplate, $targets, $request->mode(), auth()->id());
        } catch (Throwable $e) {
            return back()->with('error', 'Could not reserve a template VMID: '.$e->getMessage());
        }

        if ($builds->count() === 1) {
            return redirect()
                ->route('builds.show', $builds->first())
                ->with('success', 'Build queued. This typically takes about an hour.');
        }

        return redirect()
            ->route('templates.show', $runnerTemplate)
            ->with('success', "Queued {$builds->count()} builds ({$request->mode()}). Each node keeps serving its current template until its rebuild succeeds.");
    }
}
