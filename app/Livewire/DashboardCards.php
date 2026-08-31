<?php

namespace App\Livewire;

use App\Enums\BuildStatus;
use App\Models\ImageBuild;
use App\Models\Runner;
use Illuminate\View\View;
use Livewire\Component;

class DashboardCards extends Component
{
    public function render(): View
    {
        $stateCounts = Runner::query()
            ->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state');

        $activeBuildsCount = ImageBuild::query()
            ->whereIn('status', [BuildStatus::Queued->value, BuildStatus::Running->value])
            ->count();

        return view('livewire.dashboard-cards', [
            'stateCounts' => $stateCounts,
            'activeBuildsCount' => $activeBuildsCount,
        ]);
    }
}
