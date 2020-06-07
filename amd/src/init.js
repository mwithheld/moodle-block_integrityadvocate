define(['jquery', 'block_integrityadvocate/jquery.dataTables', 'core/log'],
        function ($, datatables) {
            return {
                init: function () {
                    // Configure element matched by selector as a DataTable,
                    // adding params to the default options.
                    // DataTable options ref https://datatables.net/reference/option/.
                    var options = {
                        'autoWidth': false,
                        'columnDefs': [
                            {'orderable': false, 'targets': [4, 5]}
                        ],
                        'info': false,
                        // Language options ref https://datatables.net/reference/option/language.
                        'language': {'search': M.str.moodle.filter + '&nbsp;'},
                        'order': [], // Disable initial sort.
                        'ordering': false,
                        'paginate': false,
                        'paging': false,
                        'searching': true
                    };
                    $('.datatable').DataTable(options);
                }
            };
        }
);
