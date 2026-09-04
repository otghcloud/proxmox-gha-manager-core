<?php

namespace App\DataTables;

use App\Helpers\DataTableHelpers;
use App\Models\RunnerTemplate;
use App\Services\Templates\TemplateUpdateService;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class RunnerTemplatesDataTable extends DataTable
{
    /**
     * @param  QueryBuilder<RunnerTemplate>  $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // Resolved once: the service reads the cached update payload from settings on every call.
        $updates = app(TemplateUpdateService::class);

        return (new EloquentDataTable($query))
            ->editColumn('name', function (RunnerTemplate $template) use ($updates): string {
                $link = '<a href="'.route('templates.show', $template).'">'.e($template->name).'</a>';
                $updateVersion = $updates->getAvailableUpdateVersion($template->template_catalog_id);

                if ($updateVersion === null) {
                    return $link;
                }

                return $link.' <span class="badge bg-warning-lt ms-1" title="Update available: '.e($updateVersion).'">'
                    .'<i class="fa-solid fa-arrow-up me-1"></i>'.e($updateVersion).' available</span>';
            })
            ->addColumn('environment', fn (RunnerTemplate $template): string => e($template->environment->name))
            ->editColumn('os', fn (RunnerTemplate $template): string => $template->os->label())
            ->addColumn('proxmox_targets_count', fn (RunnerTemplate $template): int => $template->proxmox_targets_count)
            ->addColumn('actions', function (RunnerTemplate $template): string {
                $actions = [
                    ['type' => 'view', 'href' => route('templates.show', $template)],
                ];

                $actions[] = ['type' => 'edit', 'href' => route('templates.edit', $template)];
                $actions[] = ['type' => 'delete', 'href' => route('templates.destroy', $template)];

                return DataTableHelpers::actionsDropdown($actions);
            })
            ->filterColumn('environment', fn (QueryBuilder $query, string $keyword) => $query->whereRelation('environment', 'name', 'like', "%{$keyword}%"))
            ->rawColumns(['name', 'actions'])
            ->setRowId('id');
    }

    /**
     * @return QueryBuilder<RunnerTemplate>
     */
    public function query(RunnerTemplate $model): QueryBuilder
    {
        return $model->newQuery()->with('environment')->withCount([
            'pools',
            'proxmoxTargets as proxmox_targets_count',
        ]);
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
            Column::make('os')->title('OS')->width(100),
            Column::make('proxmox_targets_count')->title('Nodes')->width(90)->searchable(false),
            Column::make('pools_count')->title('Pools')->width(80)->searchable(false),
            Column::computed('actions')->width(80)->addClass('text-end'),
        ];
    }

    protected function filename(): string
    {
        return 'Templates_'.date('YmdHis');
    }
}
