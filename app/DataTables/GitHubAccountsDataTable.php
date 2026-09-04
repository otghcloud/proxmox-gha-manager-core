<?php

namespace App\DataTables;

use App\Helpers\DataTableHelpers;
use App\Models\GitHubAccount;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class GitHubAccountsDataTable extends DataTable
{
    /**
     * @param  QueryBuilder<GitHubAccount>  $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('login', fn (GitHubAccount $account): string => '<a href="'.route('github-accounts.edit', $account).'">'.e($account->login).'</a>')
            ->editColumn('account_type', fn (GitHubAccount $account): string => ucfirst($account->account_type))
            ->editColumn('webhook_id', fn (GitHubAccount $account): string => '<span class="font-monospace">'.e($account->webhook_id).'</span>')
            ->addColumn('actions', fn (GitHubAccount $account): string => DataTableHelpers::actionsDropdown([
                ['type' => 'edit', 'href' => route('github-accounts.edit', $account)],
            ]))
            ->rawColumns(['login', 'webhook_id', 'actions'])
            ->setRowId('id');
    }

    /**
     * @return QueryBuilder<GitHubAccount>
     */
    public function query(GitHubAccount $model): QueryBuilder
    {
        return $model->newQuery()->withCount('environments');
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
            Column::make('login')->title('Login'),
            Column::make('account_type')->title('Type')->width(100),
            Column::make('webhook_id')->title('Webhook ID')->width(320)->addClass('text-nowrap'),
            Column::make('environments_count')->title('Environments')->width(120)->searchable(false),
            Column::computed('actions')->width(80)->addClass('text-end'),
        ];
    }

    protected function filename(): string
    {
        return 'GitHubAccounts_'.date('YmdHis');
    }
}
