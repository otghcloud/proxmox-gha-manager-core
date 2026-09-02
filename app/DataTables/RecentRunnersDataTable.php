<?php

namespace App\DataTables;

use App\Enums\RunnerState;
use App\Models\Runner;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class RecentRunnersDataTable extends DataTable
{
    /**
     * @param  QueryBuilder<Runner>  $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('runner_name', fn (Runner $runner): string => '<a href="'.route('runners.show', $runner).'">'.e($runner->runner_name).'</a>'
                .'<div class="text-secondary small">'.e($runner->proxmoxTarget?->name ?? '—').'</div>')
            ->addColumn('pool', fn (Runner $runner): string => e($runner->pool?->name ?? '—')
                .'<div class="text-secondary small">'.e($runner->environment->name).'</div>')
            ->editColumn('state', fn (Runner $runner): string => '<span class="badge bg-'.$runner->state->colour().'-lt runner-state">'.$runner->state->label().'</span>')
            ->editColumn('updated_at', fn (Runner $runner): string => $runner->updated_at->diffForHumans())
            ->filterColumn('pool', fn (QueryBuilder $query, string $keyword) => $query->where(fn (QueryBuilder $q) => $q->whereRelation('pool', 'name', 'like', "%{$keyword}%")->orWhereRelation('environment', 'name', 'like', "%{$keyword}%")))
            ->rawColumns(['runner_name', 'pool', 'state'])
            ->setRowId('id');
    }

    /**
     * @return QueryBuilder<Runner>
     */
    public function query(Runner $model): QueryBuilder
    {
        return $model->newQuery()
            ->with(['environment', 'pool', 'proxmoxTarget'])
            ->whereIn('state', [RunnerState::Destroyed->value, RunnerState::Failed->value])
            ->orderByDesc('updated_at');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('recentRunnersTable')
            ->columns($this->getColumns())
            ->minifiedAjax(route('dashboard.recent-runners'))
            ->setTableAttribute('data-auto-refresh', '15')
            ->pageLength(10)
            ->orderBy(3, 'desc')
            ->responsive(true)
            ->serverSide(true);
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return [
            Column::make('runner_name')->title('Runner'),
            Column::computed('pool')->title('Pool'),
            Column::make('state')->width(100),
            Column::make('updated_at')->title('Finished')->width(150)->searchable(false),
        ];
    }

    protected function filename(): string
    {
        return 'RecentRunners_'.date('YmdHis');
    }
}
