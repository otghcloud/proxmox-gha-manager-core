<?php

return [
    /*
     * DataTables JavaScript global namespace.
     */

    'namespace' => 'LaravelDataTables',

    /*
     * Default table attributes when generating the table.
     */
    'table' => [
        // 'class' => 'display table table-striped table-hover card-table datatable dt-responsive',
        'class' => 'display table table-selectable card-table table-striped datatable dt-responsive',
        'id' => 'dataTableBuilder',
    ],

    /*
     * Html builder script template.
     */
    'script' => 'datatables::script',

    /*
     * Html builder script template for DataTables Editor integration.
     */
    'editor' => 'datatables::editor',
];
