define(['jquery', 'block_integrityadvocate/jquery.dataTables', 'core/log'],
        function($, datatables) {
            return {
                test: function() {
                    window.console.log('$.fn is:');
                    window.console.log($.fn);
                    window.console.log('datatables is:');
                    window.console.log(datatables);
                },
                init: function(selector, params) {
                    // Configure element matched by selector as a DataTable,
                    // adding params to the default options.
                    if (params.debug) {
                        window.console.log('block_integrityadvocate:init.js/init(): ', selector, params);
                    }
                    var options = {
                        'autoWidth': false,
                        'paginate': false,
                        'order': [], // Disable initial sort.
                    };
                    $.extend(true, options, params); // Deep-merge params into options.
                    if (params.debug) {
                        window.console.log('block_integrityadvocate init.js/init(): options = ', options);
                    }
                    $(selector).DataTable(options);
                },
            };
        }
);
