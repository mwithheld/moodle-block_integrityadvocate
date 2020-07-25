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

                        // Ref https://stackoverflow.com/a/30503848.
                        function delay_method(label, callback, time) {
                            if (typeof window.delayed_methods == 'undefined') {
                                window.delayed_methods = {};
                            }
                            delayed_methods[label] = Date.now();
                            var t = delayed_methods[label];
                            setTimeout(function () {
                                if (delayed_methods[label] != t) {
                                    return;
                                } else {
                                    delayed_methods[label] = "";
                                    callback();
                                }
                            }, time || 500);
                        }

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

                            preventdefault(e) {
                                if (typeof e !== 'undefined') {
                                    e.preventDefault();
                                }
                                return false;
                            }

                            setup_override_form(elt) {
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
                                        .on('keyup keypress', function (e) {
                                            var keyCode = e.keyCode || e.which;
                                            debug && window.console.log('overrideui::setup_override_form::Enter pressed');
                                            if (keyCode === 13) {
                                                self.frm.elt_save.click();

                                                e.preventDefault();
                                                return false;
                                            }
                                        });

                                debug && window.console.log('overrideui::setup_override_form::Built frm', this.frm);

                                this.frm.elt_loading.hide();
                                this.save_set_status(false);
                                this.frm.trigger('reset');

                                this.frm.elt_save.on('click', function (e) {
                                    debug && window.console.log('overrideui::setup_override_form::elt_save.on.click()::Started');
                                    if (self.validate_all()) {
                                        self.save_click();
                                    }

                                    e.preventDefault();
                                    return false;
                                });
                                this.frm.elt_edit.on('click', function (e) {
                                    self.show_overrideui();
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
                                        delay_method('validate_and_toggle_save_button', function () {
                                            self.save_set_status(self.validate_all());
                                        }, 600);

                                        e.preventDefault();
                                        return false;
                                    });
                                });

                                return this;
                            }

                            cancel_click() {
                                this.frm.trigger('reset');
                                this.hide_overrideui();
                                this.frm.closest('td').find('.oldstatusinfo, .block_integrityadvocate_overriden_icon').show();
                                // Show all the override edit icons on the page.
                                $('.block_integrityadvocate_override_edit').show();
                            }

                            save_click() {
                                var self = this;
                                this.disable_ui();
                                debug && window.console.log('overrideui::save_click::Started with frm=', this.frm);

                                var url = new URL(window.location.href);

                                require(['core/ajax', 'core/notification'], function (ajax, notification) {
                                    ajax.call([{
                                            methodname: 'block_integrityadvocate_set_override',
                                            args: {
                                                status: self.frm.elt_status.val(),
                                                reason: self.frm.elt_reason.val(),
                                                targetuserid: self.frm.elt_targetuserid.val(),
                                                overrideuserid: self.frm.elt_overrideuserid.val(),
                                                blockinstanceid: url.searchParams.get('instanceid'),
                                                moduleid: self.frm.parents('tr').find('.block_integrityadvocate_participant_activitymodule').attr('data-cmid')
                                            },
                                            done: function (context) {
                                                debug && window.console.log('overrideui::save_click::ajax.done');
                                                // Set true to force reload from server not cache.  This is said to be deprecated but since we might have old browsers we'll do it.
                                                window.location.reload(true);
                                            },
                                            fail: function (xhr, textStatus, errorThrown) {
                                                debug && window.console.log('overrideui::save_click::ajax.always');
                                                console.log('textStatus', textStatus);
                                                console.log('errorThrown', errorThrown);
                                                alert(M.str.moodle.unknownerror);
                                                // Set true to force reload from server not cache.  This is said to be deprecated but since we might have old browsers we'll do it.
                                                window.location.reload(true);
                                            },
                                            always: function (context) {
                                                debug && window.console.log('overrideui::save_click::ajax.always');
                                                self.enable_ui();
                                                self.cancel_click();
                                            }
                                        }]);
                                });

                                debug && window.console.log('overrideui::save_click::Done');
                            }

                            validate_all() {
                                // We explicity want to run through both validators, so don't just AND them.
                                var isvalid_reason = this.validate_reason();
                                var isvalid_status = this.validate_status();

                                return isvalid_reason && isvalid_status;
                            }

                            validate_status() {
                                var elt = this.frm.elt_status;
                                var val = elt.val();

                                // Only values 0 and 3 are acceptable ATM.
                                return elt[0].checkValidity() || val === 0 || val === 3;
                            }

                            validate_reason() {
                                var elt = this.frm.elt_reason;
                                debug && window.console.log('overrideui::validate_reason::Started with frm=', this.frm);
                                debug && window.console.log('overrideui::validate_reason::Started with elt=', elt);

                                elt.val(elt.val().trim());

                                // Clear the custom validity message b/c having it there cause checkValidity() to return false.
                                elt[0].setCustomValidity('');

                                if (elt[0].checkValidity()) {
                                    return true;
                                } else {
                                    elt[0].setCustomValidity(M.str.block_integrityadvocate.override_reason_invalid);
                                    return false;
                                }
                            }

                            save_set_status(enabled) {
                                if (enabled) {
                                    this.save_enable();
                                } else {
                                    this.save_disable();
                                }
                            }

                            save_disable() {
                                var self = this;
                                this.frm.elt_save.css('color', 'grey').on('click.' + self.prefix + '_disable', this.preventdefault);
                            }

                            save_enable() {
                                var self = this;
                                this.frm.elt_save.css('color', '').off('click.' + self.prefix + '_disable');
                            }

                            show_overrideui() {
                                this.frm.elt_edit.hide();
                                this.frm.show();
                                // Make the save icon blink for visibility.
                                this.frm.elt_save.fadeOut(this.fadetime).fadeIn(this.fadetime);
                                this.validate_all();
                            }

                            hide_overrideui() {
                                this.frm.hide();
                                this.frm.elt_edit.fadeIn(this.fadetime);
                            }

                            disable_ui() {
                                this.frm.elt_cancel.hide();
                                this.frm.elt_save.hide();
                                this.frm.elt_loading.show();
                                $(this.frm.userinputs).each(function (_, userinput) {
                                    userinput.attr('disabled', 'disabled');
                                });
                            }

                            enable_ui() {
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
                                    $(this).append('<i class="fa fa-chevron-circle-down block_integrityadvocate_overriden_icon" aria-hidden="true" title="' + M.str.block_integrityadvocate.viewhide_overrides + '"></i>');
                                    $(this).find('i.block_integrityadvocate_overriden_icon').click(function () {
                                        debug && window.console.log('click.override fired');
                                        var tr = $(this).parents('tr');
                                        var row = dt.api().row(tr);

                                        if (row.child.isShown()) {
                                            debug && window.console.log('This row is already open - close it');
                                            row.child.hide();
                                            tr.removeClass('shown');
                                            $(this).removeClass('fa-chevron-circle-up').addClass('fa-chevron-circle-down');
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
                                            $(this).removeClass('fa-chevron-circle-down').addClass('fa-chevron-circle-up');
                                        }
                                    });
                                });

                                // If can override, show the edit icon and add click event.
                                dt_elt.find('.block_integrityadvocate_participant_session_overrideui').each(function () {
                                    var elt = $(this);
                                    // Wrap existing value (text in root element only) in a div so we can hide/show it later.
                                    var oldvalue = elt.contents().filter((_, el) => el.nodeType === 3);
                                    elt.prepend('<div class="oldstatusinfo">' + oldvalue.text() + '</div>');
                                    oldvalue.remove();

                                    elt.append('<i class="fa fa-pencil-square-o block_integrityadvocate_override_edit" aria-hidden="true" title="' + M.str.block_integrityadvocate.override_form_label + '"></i>');
                                    var o = new OverrideUi();
                                    elt.find('i.block_integrityadvocate_override_edit').click(function () {
                                        debug && window.console.log('overrideui::add_override_click::Override button clicked');
                                        var elt_overrideui;
                                        var selector_overrideform = '.block_integrityadvocate_override_form';
                                        debug && window.console.log('overrideui::add_override_click::No existing form found');
                                        elt.find('.oldstatusinfo').hide();
                                        //debug && window.console.log('overrideui::add_override_click::form', elt.find(selector_overrideform));

                                        // Add the editing form and init it.
                                        if (elt.find(selector_overrideform).length < 1) {
                                            debug && window.console.log('overrideui::add_override_click::Add the editing form and init it');

                                            // Copies the form into elt.
                                            elt.append($(selector_overrideform).first().clone());
                                            //debug && window.console.log('overrideui::add_override_click::after append elt.html=', elt.html());

                                            elt.find(selector_overrideform).show();
                                            elt_overrideui = o.setup_override_form(elt);
                                        } else {
                                            debug && window.console.log('overrideui::add_override_click::Existing form found - show it');
                                            elt.find(selector_overrideform).show();
                                        }

                                        // Hide all the override edit icons on the page.
                                        $('.block_integrityadvocate_override_edit').hide();
                                        elt.find('.block_integrityadvocate_overriden_icon').hide();
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
