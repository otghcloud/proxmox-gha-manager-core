import $ from 'jquery';
import DataTable from 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-select-bs5';
import 'laravel-datatables-vite/js/dataTables.buttons.js';
import 'laravel-datatables-vite/js/dataTables.renderers.js';

window.DataTable = DataTable;

let defaultsConfigured = false;

function configureDataTableDefaults() {
    if (defaultsConfigured) {
        return;
    }

    $.extend(true, DataTable.Buttons.defaults, {
        dom: {
            button: {
                liner: {
                    tag: '',
                },
            },
        },
    });

    $.extend(true, DataTable.ext.classes, {
        layout: {
            row: 'row justify-content-between',
        },
    });

    $.extend($.fn.dataTable.defaults, {
        lengthChange: false,
        pageLength: 25,
        layout: {
            topStart: null,
            topEnd: null,
            bottomStart: 'info',
            bottomEnd: 'paging',
        },
    });

    defaultsConfigured = true;
}

/**
 * Move the info and paging controls into the card footer so they sit outside
 * the scrollable table area.
 */
function relocateDataTableFooter(tableNode) {
    const $wrapper = $(tableNode).closest('.dt-container, .dataTables_wrapper');

    if (!$wrapper.length) {
        return;
    }

    const $footer = $wrapper.closest('.card').find('.datatable-card-footer').first();

    if (!$footer.length) {
        return;
    }

    let $layout = $footer.children('.datatable-footer-layout');

    if (!$layout.length) {
        $footer.html(
            '<div class="datatable-footer-layout d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">'
                + '<div class="datatable-footer-info"></div>'
                + '<div class="datatable-footer-pagination"></div>'
                + '</div>'
        );
        $layout = $footer.children('.datatable-footer-layout');
    }

    const $info = $wrapper.find('.dt-info, .dataTables_info').first();
    const $paging = $wrapper.find('.dt-paging, .dataTables_paginate').first();

    if ($info.length) {
        $layout.find('.datatable-footer-info').append($info);
    }

    if ($paging.length) {
        $layout.find('.datatable-footer-pagination').append($paging);
    }
}

/**
 * Wire the card header search box to the table, debounced.
 */
function bindExternalSearch(api) {
    const input = document.getElementById('advanced-table-search');

    if (!input) {
        return;
    }

    let timer = null;

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => api.search(input.value).draw(), 300);
    });
}

/**
 * Redraw when a header filter changes; their values are sent by the table's ajax data callback.
 */
function bindHeaderFilters(api) {
    document.querySelectorAll('[id^="filter-"]').forEach((select) => {
        select.addEventListener('change', () => api.draw());
    });
}

$(document).on('init.dt', (event, settings) => {
    const api = new DataTable.Api(settings);

    relocateDataTableFooter(settings.nTable);
    bindExternalSearch(api);
    bindHeaderFilters(api);

    const refreshInterval = settings.nTable.dataset.autoRefresh;
    if (refreshInterval) {
        const intervalMs = parseInt(refreshInterval, 10) * 1000;
        if (intervalMs > 0) {
            setInterval(() => {
                api.ajax.reload(null, false);
            }, intervalMs);
        }
    }
});

configureDataTableDefaults();

export default configureDataTableDefaults;
