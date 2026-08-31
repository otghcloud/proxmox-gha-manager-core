<?php

namespace App\Http\Controllers;

use App\DataTables\ActiveRunnersDataTable;
use App\DataTables\RecentRunnersDataTable;
use App\Enums\RunnerState;
use App\Models\Environment;
use App\Models\ProxmoxTarget;
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
