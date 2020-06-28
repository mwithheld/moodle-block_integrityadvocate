define(['jquery', 'jqueryui', 'block_integrityadvocate/jquery.dataTables'],
        function ($, jqui, datatables) {
            return {
                init: function () {
                    $debug = true;
                    if ($('body').hasClass('block_integrityadvocate-overview-course')) {
                        $debug && window.console.log('Found overview_participants_table');
                        // Configure element matched by selector as a DataTable, adding params to the default options.
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
                        $('#participants').DataTable(options);
                    }

                    if ($('body').hasClass('block_integrityadvocate-overview-user')) {
                        $debug && window.console.log('init.mins.js: Found overview_participant_table');
                        // Configure element matched by selector as a DataTable, adding params to the default options.
                        // DataTable options ref https://datatables.net/reference/option/.
                        // Language options ref https://datatables.net/reference/option/language.
                        var options = {
                            'autoWidth': true,
                            'language': {'search': M.str.moodle.filter + '&nbsp;'},
                            'ordering': true,
                            'order': [[2, 'desc'], [3, 'desc'], [4, 'desc']],
                            'paging': false,
                            'searching': true,
                            'scrollX': true,
                            'columnDefs': [{
                                    'targets': [0],
                                    'visible': false,
                                    'searchable': false
                                }],
                            'columns': [{
                                    'className': 'details-control',
                                    'orderable': false,
                                    'data': null,
                                    'defaultContent': ''
                                }]
//                            'init': function (settings, json) {
//                                window.console.log('DataTables.init fired');
//                            }
                        };
                        $('#block_integrityadvocate_participant_table').DataTable(options);

                        window.console.log('lenny', $('.block_integrityadvocate_participant_session_jquimodal').length);
                        $('.block_integrityadvocate_participant_session_jquimodal').click(function () {
                            $('#dialog').html('<div id="dialog" title="image"><img src="' + $(this).attr('src') + '" width="500" /></div>');
                            $('#dialog').dialog({
                                modal: true,
                                width: 'auto',
                                open: function (event, ui) {
                                    $('.ui-widget-overlay').bind('click', function () {
                                        $("#dialog").dialog('close');
                                    });
                                }
                            });
                        });
//                        function format(d) {
//                            // `d` is the original data object for the row
//                            return '<table cellpadding="5" cellspacing="0" border="0" style="padding-left:50px;">' +
//                                    '<tr>' +
//                                    '<td>Override time:</td>' +
//                                    '<td>' + d.overridetime + '</td>' +
//                                    '</tr>' +
//                                    '<tr>' +
//                                    '<td>Override original status:</td>' +
//                                    '<td>' + d.overridestatus + '</td>' +
//                                    '</tr>' +
//                                    '<tr>' +
//                                    '<td>Overrider:</td>' +
//                                    '<td>' + d.overrider + '</td>' +
//                                    '</tr>' +
//                                    '<tr>' +
//                                    '<td>Override reason:</td>' +
//                                    '<td>' + d.overridereason + '</td>' +
//                                    '</tr>' +
//                                    '</table>';
//                        }
//                        var table = $('#block_integrityadvocate_participant_table').dataTable();
//                        $('#block_integrityadvocate_participant_table tbody').off('click.override');
//                        $('#block_integrityadvocate_participant_table tbody').on('click.override', 'td.details-control', function () {
//                            window.console.log('PLUS CLICK!');
//                            var tr = $(this).parents('tr');
//                            window.console.log('tr', tr);
//                            //var row = tr.parents('table').dataTable();
//                            var dt = $('#block_integrityadvocate_participant_table').dataTable();
//                            window.console.log('table', table);
//                            var row = dt.api().row(tr);
//                            window.console.log('row', row);
//
//                            if (row.child.isShown()) {
//                                // This row is already open - close it
//                                row.child.hide();
//                                tr.removeClass('shown');
//                            } else {
//                                // Open this row
//                                var d = {
//                                    overridetime: row.data[7],
//                                    overridestatus: row.data[8],
//                                    overrider: row.data[9],
//                                    overridereason: row.data[10],
//                                };
//                                //var rowcontent=format(d);
//                                //console.log('rowcontent', rowcontent);
//                                //row.child(rowcontent).show();
//                                row.child('<div>googoogoo</div>').show();
//                                console.log('rowchild', row.child());
//                                tr.addClass('shown');
//                            }
//                        });
                    }
                }};
        }
);
