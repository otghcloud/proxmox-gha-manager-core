<?php

namespace App\DataTables;

use App\Helpers\DataTableHelpers;
use App\Models\ProxmoxTarget;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ProxmoxTargetsDataTable extends DataTable
{
    /**
     * @param  QueryBuilder<ProxmoxTarget>  $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('name', fn (ProxmoxTarget $target): string => '<a href="'.route('nodes.show', $target).'">'.e($target->name).'</a>')
            ->addColumn('status', fn (ProxmoxTarget $target): string => '<span class="badge bg-'.($target->enabled && $target->health_status === 'healthy' ? 'green' : 'secondary').'-lt">'.e($target->enabled ? ucfirst($target->health_status) : 'Disabled').'</span>')
            ->addColumn('capacity', fn (ProxmoxTarget $target): string => "{$target->current_vm_count} / {$target->max_total_vms}")
            ->addColumn('actions', fn (ProxmoxTarget $target): string => DataTableHelpers::actionsDropdown([
                [
                    'label' => 'Test connection',
                    'icon' => 'fa-solid fa-plug-circle-check fa-fw',
                    'href' => route('nodes.test', $target),
                    'attributes' => ['data-action' => 'post'],
                ],
                ['type' => 'view', 'href' => route('nodes.show', $target)],
                ['type' => 'edit', 'href' => route('nodes.edit', $target)],
                ['type' => 'delete', 'href' => route('nodes.destroy', $target), 'attributes' => [
                    'data-delete-message' => 'Deleting this node removes its template coverage and prevents new runners from using it.',
                ]],
            ]))
            ->rawColumns(['name', 'status', 'actions'])
            ->setRowId('id');
    }

    /**
     * @return QueryBuilder<ProxmoxTarget>
     */
    public function query(ProxmoxTarget $model): QueryBuilder
    {
        return $model->newQuery()->withCount(['runnerTemplates', 'runners']);
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
            Column::make('proxmox_node')->title('Node'),
            Column::make('status')->searchable(false),
            Column::make('capacity')->searchable(false),
            Column::make('runner_templates_count')->title('Templates')->searchable(false),
            Column::make('runners_count')->title('Runners')->searchable(false),
            Column::computed('actions')->width(80)->addClass('text-end'),
        ];
    }

    protected function filename(): string
    {
        return 'ProxmoxNodes_'.date('YmdHis');
    }
}
