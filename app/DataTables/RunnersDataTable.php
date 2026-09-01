<?php

namespace App\DataTables;

use App\Enums\RunnerState;
use App\Helpers\DataTableHelpers;
use App\Models\Runner;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class RunnersDataTable extends DataTable
{
    /**
     * @param  QueryBuilder<Runner>  $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('runner_name', fn (Runner $runner): string => '<a href="'.route('runners.show', $runner).'">'.e($runner->runner_name).'</a>')
            ->addColumn('pool', fn (Runner $runner): string => ($runner->pool
                ? '<a href="'.route('pools.show', $runner->pool).'">'.e($runner->pool->name).'</a>'
                : '—')
                .'<div class="text-secondary small"><a href="'.route('environments.show', $runner->environment).'">'.e($runner->environment->name).'</a></div>')
            ->addColumn('node', fn (Runner $runner): string => ($runner->proxmoxTarget
                ? '<a href="'.route('nodes.show', $runner->proxmoxTarget).'">'.e($runner->proxmoxTarget->name).'</a>'
                : '—'))
            ->addColumn('source', fn (Runner $runner): string => '<span class="badge bg-'.$runner->spawn_reason->colour().'-lt">'.$runner->spawn_reason->label().'</span>')
            ->editColumn('workflow_job_id', function (Runner $runner): string {
                if ($runner->workflow_job_id === null) {
                    return '—';
                }

                $job = $runner->servedJob;

                return $job === null
                    ? (string) $runner->workflow_job_id
                    : '<a href="'.route('jobs.show', $job).'">'.$runner->workflow_job_id.'</a>';
            })
            ->editColumn('state', fn (Runner $runner): string => '<span class="badge bg-'.$runner->state->colour().'-lt runner-state">'.$runner->state->label().'</span>')
            ->editColumn('created_at', fn (Runner $runner): string => $runner->created_at->diffForHumans())
            ->addColumn('actions', function (Runner $runner): string {
                if ($runner->state === RunnerState::Destroyed) {
                    return '';
                }

                return DataTableHelpers::actionsDropdown([
                    ['type' => 'view', 'href' => route('runners.show', $runner)],
                    ['type' => 'delete', 'label' => 'Destroy', 'href' => route('runners.destroy', $runner), 'attributes' => [
                        'data-delete-message' => 'This stops the VM, deregisters the runner and deletes its disks.',
                    ]],
                ]);
            })
            ->filterColumn('pool', fn (QueryBuilder $query, string $keyword) => $query->where(fn (QueryBuilder $q) => $q->whereRelation('pool', 'name', 'like', "%{$keyword}%")->orWhereRelation('environment', 'name', 'like', "%{$keyword}%")))
            ->orderColumn('created_at', 'created_at $1')
            ->rawColumns(['runner_name', 'pool', 'node', 'source', 'workflow_job_id', 'state', 'actions'])
            ->setRowId('id');
    }

    /**
     * @return QueryBuilder<Runner>
     */
    public function query(Runner $model): QueryBuilder
    {
        $environment = request()->input('environment');
        $state = request()->input('state');

        return $model->newQuery()
            ->with(['environment', 'pool', 'proxmoxTarget', 'servedJob'])
            ->when(is_numeric($environment) && (int) $environment > 0, fn (QueryBuilder $query) => $query->where('environment_id', (int) $environment))
            ->when(in_array($state, RunnerState::values(), true), fn (QueryBuilder $query) => $query->where('state', $state));
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('dataTable')
            ->columns($this->getColumns())
            ->minifiedAjax('', null, [
                'environment' => '$("#filter-environment").val()',
                'state' => '$("#filter-state").val()',
            ])
            ->orderBy(6, 'desc')
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
            Column::computed('node')->title('Node'),
            Column::make('vmid')->title('VMID')->width(90),
            Column::computed('source')->width(110)->searchable(false),
            Column::make('workflow_job_id')->title('Job ID')->width(120),
            Column::make('state')->width(110),
            Column::make('created_at')->title('Created')->width(140)->searchable(false),
            Column::computed('actions')->width(80)->addClass('text-end'),
        ];
    }

    protected function filename(): string
    {
        return 'Runners_'.date('YmdHis');
    }
}
