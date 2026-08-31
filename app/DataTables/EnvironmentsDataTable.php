<?php

namespace App\DataTables;

use App\Helpers\DataTableHelpers;
use App\Models\Environment;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class EnvironmentsDataTable extends DataTable
{
    /**
     * @param  QueryBuilder<Environment>  $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('name', fn (Environment $environment): string => '<a href="'.route('environments.show', $environment).'">'.e($environment->name).'</a>')
            ->addColumn('status', fn (Environment $environment): string => $environment->enabled
                ? '<span class="badge bg-green-lt">Enabled</span>'
                : '<span class="badge bg-secondary-lt">Disabled</span>')
            ->addColumn('actions', fn (Environment $environment): string => DataTableHelpers::actionsDropdown([
                ['type' => 'view', 'href' => route('environments.show', $environment)],
                ['type' => 'edit', 'href' => route('environments.edit', $environment)],
                ['type' => 'delete', 'href' => route('environments.destroy', $environment), 'attributes' => [
                    'data-delete-message' => 'Deleting this environment removes its templates, pools and runner history.',
                ]],
            ]))
            // `status` is derived from `enabled`, so ordering has to be mapped to a real column.
            ->orderColumn('status', 'enabled $1')
            ->rawColumns(['name', 'status', 'actions'])
            ->setRowId('id');
    }

    /**
     * @return QueryBuilder<Environment>
     */
    public function query(Environment $model): QueryBuilder
    {
        return $model->newQuery()->with('githubAccount')->withCount(['runnerTemplates', 'pools']);
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
            Column::make('github_account.login')->title('GitHub account'),
            Column::make('runner_templates_count')->title('Templates')->width(100)->searchable(false),
            Column::make('pools_count')->title('Pools')->width(80)->searchable(false),
            Column::make('status')->width(110)->searchable(false),
            Column::computed('actions')->width(80)->addClass('text-end'),
        ];
    }

    protected function filename(): string
    {
        return 'Environments_'.date('YmdHis');
    }
}
