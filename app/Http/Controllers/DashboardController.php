<?php

namespace App\Http\Controllers;

use App\DataTables\ActiveRunnersDataTable;
use App\DataTables\RecentRunnersDataTable;
use App\Enums\RunnerState;
use App\Models\Environment;
use App\Models\ProxmoxTarget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(ActiveRunnersDataTable $activeDataTable, RecentRunnersDataTable $recentDataTable): View
    {
        $environments = Environment::query()
            ->withCount([
                'runners as active_runners_count' => fn ($query) => $query->whereIn('state', RunnerState::activeValues()),
                'pools',
                'runnerTemplates',
            ])
            ->addSelect([
                'proxmox_targets_count' => ProxmoxTarget::selectRaw('COUNT(DISTINCT pool_proxmox_target.proxmox_target_id)')
                    ->join('pool_proxmox_target', 'proxmox_targets.id', '=', 'pool_proxmox_target.proxmox_target_id')
                    ->join('pools', 'pool_proxmox_target.pool_id', '=', 'pools.id')
                    ->where('pools.environment_id', '=', 'environments.id'),
            ])
            ->orderBy('name')
            ->get();

        return view('pages.dashboard', [
            'environments' => $environments,
            'targetCapacity' => ProxmoxTarget::sum('max_total_vms'),
            'activeRunnersTable' => $activeDataTable->html(),
            'recentRunnersTable' => $recentDataTable->html(),
        ]);
    }

    public function activeRunners(ActiveRunnersDataTable $dataTable): mixed
    {
        return $dataTable->render('pages.dashboard');
    }

    public function recentRunners(RecentRunnersDataTable $dataTable): mixed
    {
        return $dataTable->render('pages.dashboard');
    }
}
