define(['jquery', 'jqueryui', 'block_integrityadvocate/jquery.dataTables'],
        function ($, jqui, datatables) {
            return {
                init: function () {
                    var debug = true;
                    if ($('body').hasClass('block_integrityadvocate-overview-course')) {
                        debug && window.console.log('Found overview_participants_table');
                        // Configure element matched by selector as a DataTable, adding params to the default options.
                        // DataTable options ref https://datatables.net/reference/option/.
                        // Language options ref https://datatables.net/reference/option/language.
                        var options = {
                            'autoWidth': false,
                            'columnDefs': [{'orderable': false, 'targets': [4, 5]}],
                            'info': false,
                            'language': {'search': M.str.moodle.filter + '&nbsp;'},
                            'order': [], // Disable initial sort.
                            'ordering': false,
                            'paginate': false,
                            'paging': false,
                            'searching': true
                        };
                        $('#participants').DataTable(options);
                    }

                    if ($('body').hasClass('block_integrityadvocate-overview-user')) {
                        debug && window.console.log('init.mins.js: Found overview_participant_table');
                        // Configure element matched by selector as a DataTable, adding params to the default options.
                        // DataTable options ref https://datatables.net/reference/option/.
                        // Language options ref https://datatables.net/reference/option/language.
                        var options = {
                            'autoWidth': true,
                            'language': {'search': M.str.moodle.filter + '&nbsp;'},
                            'ordering': true,
                            'order': [[0, 'desc'], [1, 'desc'], [2, 'asc'], [3, 'asc']],
                            'paging': false,
                            'row-border': true,
                            'searching': true,
                            'scrollX': true,
                            'columnDefs': [{
                                    // This is the override open/close column.
                                    'targets': [6],
                                    'visible': true,
                                    'searchable': false,
                                    'orderable': false,
                                    'data': null,
                                    'defaultContent': ''
                                }],
                            'initComplete': function (settings, json) {
                                debug && window.console.log('DataTables.initComplete fired');
                                $('.block_integrityadvocate_participant_session_jquimodal').on('click.modalpic', function () {
                                    debug && window.console.log('jquimodal.click.modalpic fired');
                                    $('#dialog')
                                            .html('<div id="dialog" title="image"><img src="' + $(this).attr('src') + '" width="500" /></div>')
                                            .dialog({
                                                modal: true,
                                                width: 'auto',
                                                open: function (event, ui) {
                                                    $('.ui-widget-overlay').bind('click', function () {
                                                        $("#dialog").dialog('close');
                                                    });
                                                }
                                            });
                                });
                            }
                        };
                        $('#block_integrityadvocate_participant_table').DataTable(options);
                        function format(d) {
                            // `d` is the original data object for the row
                            return '<table cellpadding="5" cellspacing="0" border="0" style="padding-left:50px;">' +
                                    '<tr><td>Override time:</td><td>' + d.overridetime + '</td></tr>' +
                                    '<tr><td>Override original status:</td><td>' + d.overridestatus + '</td></tr>' +
                                    '<tr><td>Overrider:</td><td>' + d.overrider + '</td></tr>' +
                                    '<tr><td>Override reason:</td><td>' + d.overridereason + '</td></tr>' +
                                    '</table>';
                        }
                        var dt = $('#block_integrityadvocate_participant_table').dataTable();
                        dt.api().columns([7, 8, 9, 10]).visible(false);
                        $('#block_integrityadvocate_participant_table tbody').on('click.override', 'td.details-control', function () {
                            debug && window.console.log('click.override fired');
                            var tr = $(this).parents('tr');
                            var row = dt.api().row(tr);

                            if (row.child.isShown()) {
                                // This row is already open - close it
                                row.child.hide();
                                tr.removeClass('shown');
                            } else {
                                // Open this row
                                var d = {
                                    overridetime: row.data()[7],
                                    overridestatus: row.data()[8],
                                    overrider: row.data()[9],
                                    overridereason: row.data()[10]
                                };
                                row.child(format(d)).show();
                                tr.addClass('shown');
                            }
                        });
                    }
                }
            }
            ;
        }
);
