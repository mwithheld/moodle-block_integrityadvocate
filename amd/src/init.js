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
                        // Re-usable reference to the holder of the DataTable.
                        var dt_elt = $('#block_integrityadvocate_participant_table');

                        var build_child_row = function (dataobject) {
                            var childrow = '<table cellpadding="5" cellspacing="0" border="0" style="padding-left:50px;">' +
                                    '<tr><td>Override time:</td><td>' + dataobject.overridetime + '</td></tr>' +
                                    '<tr><td>Override original status:</td><td>' + dataobject.overridestatus + '</td></tr>' +
                                    '<tr><td>Overrider:</td><td>' + dataobject.overrider + '</td></tr>' +
                                    '<tr><td>Override reason:</td><td>' + dataobject.overridereason + '</td></tr>' +
                                    '</table>';
                            debug && window.console.log('Build childrow=', childrow);
                            return childrow;
                        };

                        // Override UI ----------------------------------------
                        class OverrideUi {
                            constructor() {
                                this.self = this;
                                this.fadetime = 300;
                                // Provide convenient access to the form components.
                                this.frm = {};
                                // Prefix used on most form items.
                                this.prefix = 'block_integrityadvocate_override';
                            }

                            preventdefault = function (e) {
                                if (typeof e !== 'undefined') {
                                    e.preventDefault();
                                }
                                return false;
                            }

                            setup_override_form = function (elt) {
                                debug && window.console.log('overrideui::setup_override_form::Started');
                                var self = this;

                                // Make easy-to-read refs to the form elements.
                                this.frm = elt.find('.' + this.prefix + '_form');
                                // The edit icon is outside the form.
                                this.frm.elt_edit = elt.find('.' + this.prefix + '_edit');

                                this.frm.elt_status = this.frm.find('.' + this.prefix + '_status_select');
                                this.frm.elt_reason = this.frm.find('.' + this.prefix + '_reason');
                                this.frm.userinputs = [this.frm.elt_status, this.frm.elt_reason];
                                this.frm.elt_targetuserid = this.frm.find('.' + this.prefix + '_targetuserid');
                                this.frm.elt_overrideuserid = this.frm.find('.' + this.prefix + '_overrideuserid');
                                this.frm.elt_save = this.frm.find('.' + this.prefix + '_save');
                                this.frm.elt_loading = this.frm.find('.' + this.prefix + '_loading');
                                this.frm.elt_cancel = this.frm.find('.' + this.prefix + '_cancel');

                                this.frm.elt_reason
                                        .attr('placeholder', M.str.block_integrityadvocate.override_reason_label)
                                        .attr('pattern', '^[a-zA-Z0-9\ .,_-]{0,32}')
                                        .on('keypress.' + this.prefix + '_disable', function (e) {
                                            if (e.which == 13) {
                                                this.frm.elt_save.click();
                                                e.preventDefault();
                                                return false;
                                            }
                                        });

                                debug && window.console.log('overrideui::setup_override_form::Built frm', this.frm);

                                this.frm.elt_loading.hide();
                                this.save_set_status(false);
                                this.frm.trigger('reset');

                                this.frm.elt_save.on('click', function (e) {
                                    if (this.validate_all()) {
                                        this.save_click();
                                    }
                                    e.preventDefault();
                                    return false;
                                });
                                this.frm.elt_edit.on('click', function (e) {
                                    this.show_overrideui();
                                    e.preventDefault();
                                    return false;
                                });
                                this.frm.elt_cancel.on('click', function (e) {
                                    self.cancel_click();
                                    e.preventDefault();
                                    return false;
                                });
                                $(this.frm.userinputs).each(function (_, userinput) {
                                    userinput.on('change keyup blur', function (e) {
                                        this.save_set_status(this.validate_all());
                                        e.preventDefault();
                                        return false;
                                    });
                                });

                                return this;
                            }

                            cancel_click = function () {
                                this.frm.trigger('reset');
                                this.hide_overrideui();
                                $('.oldstatusinfo').show();
                            }

                            save_click = function () {
                                this.disable_ui();

                                var url = new URL(window.location.href);

                                require(['core/ajax', 'core/notification'], function (ajax, notification) {
                                    ajax.call([{
                                            methodname: 'block_integrityadvocate_set_override',
                                            args: {status: this.frm.elt_status.val(), reason: this.frm.elt_reason.val(), targetuserid: this.frm.elt_targetuserid.val(), overrideuserid: this.frm.elt_overrideuserid.val(), blockinstanceid: url.searchParams.get('instanceid')},
                                            done: function (context) {
                                                // Set true to force reload from server not cache.  This is said to be deprecated but since we might have old browsers we'll do it.
                                                window.location.reload(true);
                                            },
                                            fail: notification.exception,
                                            always: function (context) {
                                                this.enable_ui();
                                                cancel_click();
                                            }
                                        }]);
                                });
                            }

                            validate_all = function () {
                                // We explicity want to run through both validators, so don't just AND them.
                                var isvalid_reason = this.validate_reason();
                                var isvalid_status = this.validate_status();

                                return isvalid_reason && isvalid_status;
                            }

                            validate_status = function () {
                                var elt = this.frm.elt_status;
                                var val = elt.val();

                                // Only values 0 and 3 are acceptable ATM.
                                return elt[0].checkValidity() || val === 0 || val === 3;
                            }

                            validate_reason = function () {
                                var elt = this.frm.elt_reason;
                                var val = elt.val();
                                debug && window.console.log('overrideui::validate_reason::Started with frm=', this.frm);
                                debug && window.console.log('overrideui::validate_reason::Started with elt=', elt);

                                // Clear the custom validity message b/c having it there cause checkValidity() to return false.
                                elt[0].setCustomValidity('');

                                if (val === '' || elt[0].checkValidity()) {
                                    return true;
                                } else {
                                    elt[0].setCustomValidity(M.str.block_integrityadvocate.override_reason_invalid);
                                    return false;
                                }
                            }

                            save_set_status = function (enabled) {
                                if (enabled) {
                                    this.save_enable();
                                } else {
                                    this.save_disable();
                                }
                            }

                            save_disable = function () {
                                this.frm.elt_save.css('color', 'grey').on('click.' + this.prefix + '_disable', this.preventdefault);
                            }

                            save_enable = function () {
                                this.frm.elt_save.css('color', '').off('click.' + this.prefix + '_disable');
                            }

                            show_overrideui = function () {
                                this.frm.elt_edit.hide();
                                this.frm.show();
                                // Make the save icon blink a bit for visibility.
                                this.frm.elt_save.fadeOut(this.fadetime).fadeIn(this.fadetime).fadeOut(this.fadetime).fadeIn(this.fadetime);
                                this.validate_all();
                            }

                            hide_overrideui = function () {
                                this.frm.hide();
                                this.frm.elt_edit.fadeIn(this.fadetime);
                            }

                            disable_ui = function () {
                                this.frm.elt_cancel.hide();
                                this.frm.elt_save.hide();
                                this.frm.elt_loading.show();
                                $(this.frm.userinputs).each(function (_, userinput) {
                                    userinput.attr('disabled', 'disabled');
                                });
                            }

                            enable_ui = function () {
                                this.frm.elt_cancel.show();
                                this.frm.elt_save.show();
                                this.frm.elt_loading.hide();
                                $(this.frm.userinputs).each(function (_, userinput) {
                                    userinput.removeAttr('disabled');
                                });
                            }
                        }
                        // End Override UI ------------------------------------

                        // Configure element matched by selector as a DataTable, adding params to the default options.
                        // DataTable options ref https://datatables.net/reference/option/.
                        // Language options ref https://datatables.net/reference/option/language.
                        var options = {
                            'autoWidth': true,
                            'language': {'search': M.str.moodle.filter + '&nbsp;'},
                            'ordering': true,
                            'order': [[0, 'desc'], [1, 'desc']],
                            'paging': false,
                            'row-border': true,
                            'searching': true,
                            'scrollX': true,
                            'columnDefs': [
                                {
                                    'targets': [4],
                                    'orderable': false,
                                    'searchable': false
                                }],
                            'initComplete': function (settings, json) {
                                debug && window.console.log('DataTables.initComplete fired');
                                // Re-usable reference to the DataTable.
                                var dt = dt_elt.dataTable();
                                // Hide the override columns.
                                dt.api().columns([6, 7, 8, 9]).visible(false);

                                // If overridden, show more info in child row.
                                dt_elt.find('.block_integrityadvocate_participant_session_overridden').each(function () {
                                    $(this).append('<i class="fa fa-info-circle" aria-hidden="true" title="' + M.str.block_integrityadvocate.overridden + '"></i>')
                                            .find('i').click(function () {
                                        debug && window.console.log('click.override fired');
                                        var tr = $(this).parents('tr');
                                        var row = dt.api().row(tr);

                                        if (row.child.isShown()) {
                                            debug && window.console.log('This row is already open - close it');
                                            row.child.hide();
                                            tr.removeClass('shown');
                                        } else {
                                            debug && window.console.log('Open this row');
                                            var d = {
                                                overridetime: row.data()[6],
                                                overridestatus: row.data()[7],
                                                overrider: row.data()[8],
                                                overridereason: row.data()[9]
                                            };
                                            row.child(build_child_row(d)).show();
                                            tr.addClass('shown');
                                        }
                                    });
                                });

                                // If can override, show the edit icon and add click event.
                                dt_elt.find('.block_integrityadvocate_participant_session_overrideui').each(function () {
                                    var elt = $(this);
                                    elt.append('<i class="fa fa-pencil-square-o" aria-hidden="true" title="' + M.str.block_integrityadvocate.override_form_label + '"></i>');
                                    var o = new OverrideUi();
//                                    o.add_override_click(elt);
                                    elt.find('i').click(function () {
                                        debug && window.console.log('overrideui::add_override_click::Override button clicked');
                                        var overrideui_elt;
                                        var elt_selector = '.block_integrityadvocate_override_form';
                                        if (elt.find(elt_selector).length < 1) {
                                            debug && window.console.log('overrideui::add_override_click::No existing form found');
                                            // Wrap the current content in a div so we can hide it and re-show it later.
                                            elt.html('<div class="oldstatusinfo" style="display:none">' + elt.html() + '</div>');
                                            debug && window.console.log('overrideui::add_override_click::form', elt.find(elt_selector));
                                            // Add the editing form and init it.
                                            elt.append($(elt_selector));
                                            debug && window.console.log('overrideui::add_override_click::after append elt.html=', elt.html());

                                            elt.find(elt_selector).show();
                                            overrideui_elt = o.setup_override_form(elt);
                                        } else {
                                            debug && window.console.log('overrideui::add_override_click::Existing form found');
                                            // Hide the old status info.
                                            elt.find('.oldstatusinfo').hide();
                                            // Show the existing form.
                                            elt.find(elt_selector).show();
                                        }
                                        o.show_overrideui();
                                    });
                                });

                                // Show user picture full size in modal.
                                dt_elt.find('.block_integrityadvocate_participant_session_jquimodal').on('click.modalpic', function () {
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
                        dt_elt.dataTable(options);
                    }
                }
            };
        }
);
