<?php

namespace App\DataTables;

use App\Helpers\DataTableHelpers;
use App\Models\Pool;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PoolsDataTable extends DataTable
{
    /**
     * @param  QueryBuilder<Pool>  $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('name', fn (Pool $pool): string => '<a href="'.route('pools.show', $pool).'">'.e($pool->name).'</a>')
            ->addColumn('environment', fn (Pool $pool): string => e($pool->environment->name))
            ->addColumn('template', fn (Pool $pool): string => e($pool->runnerTemplate?->name ?? '—'))
            ->addColumn('labels', fn (Pool $pool): string => collect($pool->labels)
                ->map(fn (string $label): string => '<span class="badge bg-blue-lt">'.e($label).'</span>')
                ->implode(' '))
            ->addColumn('capacity', fn (Pool $pool): string => $pool->activeRunnerCount().' / '.$pool->totalMaxConcurrent().($pool->totalMinIdleRunners() > 0 ? ' <span class="text-secondary small">('.$pool->totalMinIdleRunners().' idle)</span>' : ''))
            ->addColumn('status', fn (Pool $pool): string => $pool->enabled
                ? '<span class="badge bg-green-lt">Enabled</span>'
                : '<span class="badge bg-secondary-lt">Disabled</span>')
            ->addColumn('actions', fn (Pool $pool): string => DataTableHelpers::actionsDropdown([
                ['type' => 'view', 'href' => route('pools.show', $pool)],
                ['type' => 'edit', 'href' => route('pools.edit', $pool)],
                ['type' => 'delete', 'href' => route('pools.destroy', $pool)],
            ]))
            ->filterColumn('environment', fn (QueryBuilder $query, string $keyword) => $query->whereRelation('environment', 'name', 'like', "%{$keyword}%"))
            ->filterColumn('template', fn (QueryBuilder $query, string $keyword) => $query->whereRelation('runnerTemplate', 'name', 'like', "%{$keyword}%"))
            ->rawColumns(['name', 'labels', 'capacity', 'status', 'actions'])
            ->setRowId('id');
    }

    /**
     * @return QueryBuilder<Pool>
     */
    public function query(Pool $model): QueryBuilder
    {
        return $model->newQuery()->with(['environment', 'runnerTemplate', 'proxmoxTargets']);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('dataTable')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0, 'asc')
            ->responsive(true)
            ->serverSide(true);
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return [
            Column::make('name'),
            Column::computed('environment'),
            Column::computed('template'),
            Column::computed('labels')->searchable(false),
            Column::computed('capacity')->width(140),
            Column::computed('status')->width(100),
            Column::computed('actions')->width(80)->addClass('text-end'),
        ];
    }

    protected function filename(): string
    {
        return 'Pools_'.date('YmdHis');
    }
}
