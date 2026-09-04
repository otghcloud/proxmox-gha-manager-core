<?php

namespace App\DataTables;

use App\Helpers\DataTableHelpers;
use App\Models\ImageBuild;
use App\Services\Builds\TemplateCatalog;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class BuildsDataTable extends DataTable
{
    /**
     * @param  QueryBuilder<ImageBuild>  $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('template_target', function (ImageBuild $build): string {
                $name = app(TemplateCatalog::class)->entryForId($build->template_catalog_id)?->name() ?? $build->template_catalog_id;
                $version = $build->version ? ' <span class="text-secondary small">('.e($build->version).')</span>' : '';

                return '<a href="'.route('builds.show', $build).'">'.e($name).'</a>'.$version;
            })
            ->addColumn('environment', fn (ImageBuild $build): string => e($build->environment->name))
            ->addColumn('template', fn (ImageBuild $build): string => e($build->runnerTemplate?->name ?? '—'))
            ->addColumn('node', fn (ImageBuild $build): string => e($build->proxmoxTarget?->name ?? '—'))
            ->editColumn('status', fn (ImageBuild $build): string => '<span class="badge bg-'.$build->status->colour().'-lt">'.$build->status->label().'</span>')
            ->addColumn('triggered', fn (ImageBuild $build): string => e($build->triggeredBy?->name ?? 'System'))
            ->editColumn('started_at', fn (ImageBuild $build): string => $build->started_at?->diffForHumans() ?? '—')
            ->editColumn('finished_at', fn (ImageBuild $build): string => $build->finished_at?->diffForHumans() ?? '—')
            ->addColumn('actions', function (ImageBuild $build): string {
                $actions = [
                    ['type' => 'view', 'href' => route('builds.show', $build)],
                ];

                if ($build->status->isFinished()) {
                    $actions[] = ['type' => 'delete', 'href' => route('builds.destroy', $build), 'attributes' => [
                        'data-delete-message' => 'This removes the build record and its stored log file.',
                    ]];
                } else {
                    $actions[] = [
                        'type' => 'custom',
                        'label' => 'Force kill',
                        'icon' => 'fa-solid fa-skull fa-fw',
                        'class' => 'text-danger',
                        'href' => route('builds.cancel', $build),
                        'attributes' => ['data-action' => 'post'],
                    ];
                }

                return DataTableHelpers::actionsDropdown($actions);
            })
            ->filterColumn('environment', fn (QueryBuilder $query, string $keyword) => $query->whereRelation('environment', 'name', 'like', "%{$keyword}%"))
            ->filterColumn('template', fn (QueryBuilder $query, string $keyword) => $query->whereRelation('runnerTemplate', 'name', 'like', "%{$keyword}%"))
            ->rawColumns(['template_target', 'status', 'actions'])
            ->setRowId('id');
    }

    /**
     * @return QueryBuilder<ImageBuild>
     */
    public function query(ImageBuild $model): QueryBuilder
    {
        return $model->newQuery()->with(['environment', 'runnerTemplate', 'proxmoxTarget', 'triggeredBy']);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('dataTable')
            ->columns($this->getColumns())
            ->minifiedAjax()
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
            Column::computed('template_target')->title('Target'),
            Column::computed('environment'),
            Column::computed('template'),
            Column::computed('node')->title('Node'),
            Column::make('status')->width(110),
            Column::computed('triggered')->title('Triggered by')->width(140),
            Column::make('started_at')->title('Started')->width(140)->searchable(false),
            Column::make('finished_at')->title('Finished')->width(140)->searchable(false),
            Column::computed('actions')->width(80)->addClass('text-end'),
        ];
    }

    protected function filename(): string
    {
        return 'Builds_'.date('YmdHis');
    }
}
