<?php

namespace App\DataTables;

use App\Enums\JobConclusion;
use App\Helpers\DataTableHelpers;
use App\Models\WorkflowJob;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class WorkflowJobsDataTable extends DataTable
{
    /**
     * @param  QueryBuilder<WorkflowJob>  $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('job_name', fn (WorkflowJob $job): string => '<a href="'.route('jobs.show', $job).'">'.e($job->job_name).'</a>'
                .($job->workflow_name ? '<div class="text-secondary small">'.e($job->workflow_name).'</div>' : ''))
            ->editColumn('repository_full_name', fn (WorkflowJob $job): string => e($job->repositoryName())
                .($job->head_branch ? '<div class="text-secondary small">'.e($job->head_branch).'</div>' : ''))
            ->addColumn('environment', fn (WorkflowJob $job): string => e($job->environment->name))
            ->addColumn('runner', fn (WorkflowJob $job): string => $job->runner === null
                ? e($job->runner_name ?? '—')
                : '<a href="'.route('runners.show', $job->runner).'">'.e($job->runner->runner_name).'</a>')
            ->editColumn('github_job_id', fn (WorkflowJob $job): string => (string) $job->github_job_id)
            ->addColumn('result', fn (WorkflowJob $job): string => $job->conclusion === null
                ? '<span class="badge bg-secondary-lt">'.e(ucfirst(str_replace('_', ' ', $job->status))).'</span>'
                : '<span class="badge bg-'.$job->conclusion->colour().'-lt">'.e($job->conclusion->label()).'</span>')
            ->addColumn('duration', fn (WorkflowJob $job): string => DataTableHelpers::duration($job->durationSeconds()))
            ->editColumn('created_at', fn (WorkflowJob $job): string => $job->created_at->diffForHumans())
            ->addColumn('actions', fn (WorkflowJob $job): string => DataTableHelpers::actionsDropdown([
                ['type' => 'view', 'href' => route('jobs.show', $job)],
                ['type' => 'delete', 'href' => route('jobs.destroy', $job)],
            ]))
            ->filterColumn('environment', fn (QueryBuilder $query, string $keyword) => $query->whereRelation('environment', 'name', 'like', "%{$keyword}%"))
            ->filterColumn('runner', fn (QueryBuilder $query, string $keyword) => $query->where('runner_name', 'like', "%{$keyword}%"))
            ->orderColumn('created_at', 'created_at $1')
            ->rawColumns(['job_name', 'repository_full_name', 'runner', 'result', 'actions'])
            ->setRowId('id');
    }

    /**
     * @return QueryBuilder<WorkflowJob>
     */
    public function query(WorkflowJob $model): QueryBuilder
    {
        $environment = request()->input('environment');
        $conclusion = request()->input('conclusion');

        return $model->newQuery()
            ->with(['environment', 'runner'])
            ->when(is_numeric($environment) && (int) $environment > 0, fn (QueryBuilder $query) => $query->where('environment_id', (int) $environment))
            ->when(in_array($conclusion, array_column(JobConclusion::cases(), 'value'), true), fn (QueryBuilder $query) => $query->where('conclusion', $conclusion));
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('dataTable')
            ->columns($this->getColumns())
            ->minifiedAjax('', null, [
                'environment' => '$("#filter-environment").val()',
                'conclusion' => '$("#filter-conclusion").val()',
            ])
            ->orderBy(7, 'desc')
            ->responsive(true)
            ->serverSide(true);
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return [
            Column::make('job_name')->title('Job'),
            Column::make('repository_full_name')->title('Repository'),
            Column::computed('environment'),
            Column::computed('runner')->title('Runner'),
            Column::make('github_job_id')->title('Job ID')->width(120)->visible(false),
            Column::computed('result')->width(110),
            Column::computed('duration')->width(100)->searchable(false),
            Column::make('created_at')->title('Seen')->width(150)->searchable(false),
            Column::computed('actions')->width(80)->addClass('text-end'),
        ];
    }

    protected function filename(): string
    {
        return 'jobs-'.date('YmdHis');
    }
}
