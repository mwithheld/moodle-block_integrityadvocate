define(['jquery', 'jqueryui', 'block_integrityadvocate/jquery.dataTables'],
        function($, jqui, datatables) {
            return {
                init: function() {
                    var debug = true;
                    switch (true) {
                        case($('body').hasClass('block_integrityadvocate-overview-course')):
                            debug && window.console.log('M.block_integrityadvocate.init.js::Found overview_participants_table - DataTables adds the filter capability');
                            // Configure element matched by selector as a DataTable, adding params to the default options.
                            // DataTable options ref https://datatables.net/reference/option/.
                            // Language options ref https://datatables.net/reference/option/language.
                            $('#participants').DataTable({
                                'autoWidth': false,
                                'columnDefs': [{'orderable': false, 'targets': [4, 5]}],
                                'info': false,
                                'language': {'search': M.util.get_string('filter', 'moodle') + '&nbsp;'},
                                'order': [], // Disable initial sort.
                                'ordering': false,
                                'paginate': false,
                                'paging': false,
                                'searching': true
                            });
                            break;

                        case($('body').hasClass('block_integrityadvocate-overview-user') || $('body').hasClass('block_integrityadvocate-overview-module')):
                            debug && window.console.log('M.block_integrityadvocate.init.js::Found overview_participant_table - DataTables is the wholeUI');
                            // Re-usable reference to the holder of the DataTable.
                            var eltDt = $('#block_integrityadvocate_participant_table');

                            // Ref https://stackoverflow.com/a/30503848.
                            /**
                             * Delay the callback until time has elapsed.
                             * Repeated calls only runs the last one.
                             * @param string label Calls with the same label reset the timer.
                             * @param function callback The function to execute.
                             * @param int time Time to delay the callback.
                             * @returns null Nothing.
                             */
                            function delayMethod(label, callback, time) {
                                if (typeof window.delayed_methods == 'undefined') {
                                    window.delayed_methods = {};
                                }
                                delayed_methods[label] = Date.now();
                                var t = delayed_methods[label];
                                setTimeout(function() {
                                    if (delayed_methods[label] != t) {
                                        return;
                                    } else {
                                        delayed_methods[label] = "";
                                        callback();
                                    }
                                }, time || 500);
                            }

                            var buildChildRow = function(dataobject) {
                                var childrow = '<table cellpadding="5" cellspacing="0" border="0" style="padding-left:50px;">' +
                                        '<tr><td>Override time:</td><td>' + dataobject.overridetime + '</td></tr>' +
                                        '<tr><td>Override original status:</td><td>' + dataobject.overridestatus + '</td></tr>' +
                                        '<tr><td>Overrider:</td><td>' + dataobject.overrider + '</td></tr>' +
                                        '<tr><td>Override reason:</td><td>' + dataobject.overridereason + '</td></tr>' +
                                        '</table>';
                                debug && window.console.log('M.block_integrityadvocate.init.js::Built childrow=', childrow);
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

                                overrideuiSetup(elt) {
                                    debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::overrideuiSetup::Started');
                                    var self = this;

                                    // Make easy-to-read refs to the form elements.
                                    this.frm = elt.find('.' + this.prefix + '_form');
                                    // The edit icon is outside the form.
                                    this.frm.eltEdit = elt.find('.' + this.prefix + '_edit');

                                    this.frm.eltStatus = this.frm.find('.' + this.prefix + '_status_select');
                                    this.frm.eltReason = this.frm.find('.' + this.prefix + '_reason');
                                    this.frm.arrUserinputs = [this.frm.eltStatus, this.frm.eltReason];
                                    this.frm.eltTargetuserid = this.frm.find('.' + this.prefix + '_targetuserid');
                                    this.frm.eltOverrideuserid = this.frm.find('.' + this.prefix + '_overrideuserid');
                                    this.frm.eltSave = this.frm.find('.' + this.prefix + '_save');
                                    this.frm.eltLoading = this.frm.find('.' + this.prefix + '_loading');
                                    this.frm.eltCancel = this.frm.find('.' + this.prefix + '_cancel');

                                    this.frm.eltReason
                                            .attr('placeholder', M.util.get_string('block_integrityadvocate', 'override_reason_label'))
                                            .attr('pattern', '^[a-zA-Z0-9\ .,_-]{0,32}')
                                            .on('keyup keypress', function(e) {
                                                var keyCode = e.keyCode || e.which;
                                                debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::overrideuiSetup::Enter pressed');
                                                if (keyCode === 13) {
                                                    self.frm.eltSave.click();

                                                    e.preventDefault();
                                                    return false;
                                                }
                                            });

                                    debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::overrideuiSetup::Built frm', this.frm);

                                    this.frm.eltLoading.hide();
                                    this.saveSetStatus(false);
                                    this.frm.trigger('reset');

                                    this.frm.eltSave.on('click', function(e) {
                                        debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::overrideuiSetup::eltSave.on.click()::Started');
                                        if (self.validateAll()) {
                                            self.saveClick();
                                        }

                                        e.preventDefault();
                                        return false;
                                    });
                                    this.frm.eltEdit.on('click', function(e) {
                                        self.overrideuiShow();
                                        e.preventDefault();
                                        return false;
                                    });
                                    this.frm.eltCancel.on('click', function(e) {
                                        self.cancelClick();
                                        e.preventDefault();
                                        return false;
                                    });
                                    $(this.frm.arrUserinputs).each(function(_, userinput) {
                                        userinput.on('change keyup blur', function(e) {
                                            delayMethod('validate_and_toggle_save_button', function() {
                                                self.saveSetStatus(self.validateAll());
                                            }, 600);

                                            e.preventDefault();
                                            return false;
                                        });
                                    });

                                    return this;
                                }

                                cancelClick() {
                                    this.frm.trigger('reset');
                                    this.overrideuiHide();
                                    this.frm.closest('td').find('.oldstatusinfo, .block_integrityadvocate_overriden_icon').show();
                                    // Show all the override edit icons on the page.
                                    $('.block_integrityadvocate_override_edit').show();
                                }

                                saveClick() {
                                    var self = this;
                                    this.inputsDisable();
                                    debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::saveClick::Started with frm=', this.frm);

                                    var url = new URL(window.location.href);

                                    require(['core/ajax'], function(ajax) {
                                        ajax.call([{
                                                methodname: 'block_integrityadvocate_set_override',
                                                args: {
                                                    status: self.frm.eltStatus.val(),
                                                    reason: self.frm.eltReason.val(),
                                                    targetuserid: self.frm.eltTargetuserid.val(),
                                                    overrideuserid: self.frm.eltOverrideuserid.val(),
                                                    blockinstanceid: url.searchParams.get('instanceid'),
                                                    moduleid: self.frm.parents('tr').find('.block_integrityadvocate_participant_activitymodule').attr('data-cmid')
                                                },
                                                done: function() {
                                                    debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::saveClick::ajax.done');
                                                    // Set true to force reload from server not cache.  This is said to be deprecated but since we might have old browsers we'll do it.
                                                    window.location.reload(true);
                                                },
                                                fail: function(xhr_unused, textStatus, errorThrown) {
                                                    debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::saveClick::ajax.always');
                                                    console.log('textStatus', textStatus);
                                                    console.log('errorThrown', errorThrown);
                                                    alert(M.util.get_string('moodle', 'unknownerror'));
                                                    // Set true to force reload from server not cache.  This is said to be deprecated but since we might have old browsers we'll do it.
                                                    window.location.reload(true);
                                                },
                                                always: function() {
                                                    debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::saveClick::ajax.always');
                                                    self.inputsEnable();
                                                    self.cancelClick();
                                                }
                                            }]);
                                    });

                                    debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::saveClick::Done');
                                }

                                validateAll() {
                                    // We explicity want to run through both validators to trigger the validators, so don't just AND them.
                                    var isValidReason = this.validateReason();
                                    var isValidStatus = this.validateStatus();

                                    return isValidReason && isValidStatus;
                                }

                                validateStatus() {
                                    var elt = this.frm.eltStatus;
                                    var val = elt.val();

                                    // Only values 0 and 3 are acceptable ATM.
                                    return elt[0].checkValidity() || val === 0 || val === 3;
                                }

                                validateReason() {
                                    var elt = this.frm.eltReason;
                                    debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::validateReason::Started with frm=', this.frm);
                                    debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::validateReason::Started with elt=', elt);

                                    elt.val(elt.val().trim());

                                    // Clear the custom validity message b/c having it there cause checkValidity() to return false.
                                    elt[0].setCustomValidity('');

                                    if (elt[0].checkValidity()) {
                                        return true;
                                    } else {
                                        elt[0].setCustomValidity(M.util.get_string('block_integrityadvocate', 'override_reason_invalid'));
                                        return false;
                                    }
                                }

                                saveSetStatus(enabled) {
                                    if (enabled) {
                                        this.saveEnable();
                                    } else {
                                        this.saveDisable();
                                    }
                                }

                                saveDisable() {
                                    var self = this;
                                    this.frm.eltSave.css('color', 'grey').on('click.' + self.prefix + '_disable', this.preventdefault);
                                }

                                saveEnable() {
                                    var self = this;
                                    this.frm.eltSave.css('color', '').off('click.' + self.prefix + '_disable');
                                }

                                overrideuiShow() {
                                    this.frm.eltEdit.hide();
                                    this.frm.show();
                                    // Make the save icon blink for visibility.
                                    this.frm.eltSave.fadeOut(this.fadetime).fadeIn(this.fadetime);
                                    this.validateAll();
                                }

                                overrideuiHide() {
                                    this.frm.hide();
                                    this.frm.eltEdit.fadeIn(this.fadetime);
                                }

                                inputsDisable() {
                                    this.frm.eltCancel.hide();
                                    this.frm.eltSave.hide();
                                    this.frm.eltLoading.show();
                                    $(this.frm.arrUserinputs).each(function(_, userinput) {
                                        userinput.attr('disabled', 'disabled');
                                    });
                                }

                                inputsEnable() {
                                    this.frm.eltCancel.show();
                                    this.frm.eltSave.show();
                                    this.frm.eltLoading.hide();
                                    $(this.frm.arrUserinputs).each(function(_, userinput) {
                                        userinput.removeAttr('disabled');
                                    });
                                }
                            }
                            // End Override UI ------------------------------------

                            // Configure element matched by selector as a DataTable, adding params to the default options.
                            // DataTable options ref https://datatables.net/reference/option/.
                            // Language options ref https://datatables.net/reference/option/language.
                            eltDt.dataTable({
                                'autoWidth': true,
                                'language': {'search': M.util.get_string('filter', 'moodle') + '&nbsp;'},
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
                                'initComplete': function() {
                                    debug && window.console.log('M.block_integrityadvocate.init.js::DataTables.initComplete:fired');
                                    // Re-usable reference to the DataTable.
                                    var dt = eltDt.dataTable();
                                    // Hide the override columns.
                                    dt.api().columns([6, 7, 8, 9]).visible(false);

                                    // If overridden, show more info in child row.
                                    eltDt.find('.block_integrityadvocate_participant_session_overridden').each(function() {
                                        $(this).append('<i class="fa fa-chevron-circle-down block_integrityadvocate_overriden_icon" aria-hidden="true" title="' + M.util.get_string('block_integrityadvocate', 'viewhide_overrides') + '"></i>');
                                        $(this).find('i.block_integrityadvocate_overriden_icon').click(function() {
                                            debug && window.console.log('M.block_integrityadvocate.init.js::overridden_icon.click fired');
                                            var tr = $(this).parents('tr');
                                            var row = dt.api().row(tr);

                                            if (row.child.isShown()) {
                                                debug && window.console.log('M.block_integrityadvocate.init.js::overridden_icon.click:This row is already open - close it');
                                                row.child.hide();
                                                tr.removeClass('shown');
                                                $(this).removeClass('fa-chevron-circle-up').addClass('fa-chevron-circle-down');
                                            } else {
                                                debug && window.console.log('M.block_integrityadvocate.init.js::overridden_icon.click:Open this row');
                                                var d = {
                                                    overridetime: row.data()[6],
                                                    overridestatus: row.data()[7],
                                                    overrider: row.data()[8],
                                                    overridereason: row.data()[9]
                                                };
                                                row.child(buildChildRow(d)).show();
                                                tr.addClass('shown');
                                                $(this).removeClass('fa-chevron-circle-down').addClass('fa-chevron-circle-up');
                                            }
                                        });
                                    });

                                    // If can override, show the edit icon and add click event.
                                    eltDt.find('.block_integrityadvocate_participant_session_overrideui').each(function() {
                                        var elt = $(this);
                                        // Wrap existing value (text in root element only) in a div so we can hide/show it later.
                                        var oldvalue = elt.contents().filter((_, el) => el.nodeType === 3);
                                        elt.prepend('<div class="oldstatusinfo">' + oldvalue.text() + '</div>');
                                        oldvalue.remove();

                                        elt.append('<i class="fa fa-pencil-square-o block_integrityadvocate_override_edit" aria-hidden="true" title="' + M.util.get_string('block_integrityadvocate', 'override_form_label') + '"></i>');
                                        var o = new OverrideUi();
                                        elt.find('i.block_integrityadvocate_override_edit').click(function() {
                                            debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::add_override_click::Override button clicked');
                                            var eltOverrideui;
                                            var selectorOverrideform = '.block_integrityadvocate_override_form';
                                            debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::add_override_click::No existing form found');
                                            elt.find('.oldstatusinfo').hide();

                                            // Add the editing form and init it.
                                            if (elt.find(selectorOverrideform).length < 1) {
                                                debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::add_override_click::Add the editing form and init it');

                                                // Copies the form into elt.
                                                elt.append($(selectorOverrideform).first().clone());

                                                elt.find(selectorOverrideform).show();
                                                eltOverrideui = o.overrideuiSetup(elt);
                                            } else {
                                                debug && window.console.log('M.block_integrityadvocate.init.js::overrideui::add_override_click::Existing form found - show it');
                                                elt.find(selectorOverrideform).show();
                                            }

                                            // Hide all the override edit icons on the page.
                                            $('.block_integrityadvocate_override_edit').hide();
                                            elt.find('.block_integrityadvocate_overriden_icon').hide();
                                            o.overrideuiShow();
                                        });
                                    });

                                    // Show user picture full size in modal.
                                    eltDt.find('.block_integrityadvocate_participant_session_jquimodal').on('click.modalpic', function() {
                                        debug && window.console.log('M.block_integrityadvocate.init.js::jquimodal.click.modalpic fired');
                                        $('#dialog')
                                                .html('<div id="dialog" title="image"><img src="' + $(this).attr('src') + '" width="500" /></div>')
                                                .dialog({
                                                    modal: true,
                                                    width: 'auto',
                                                    open: function() {
                                                        $('.ui-widget-overlay').bind('click', function() {
                                                            $("#dialog").dialog('close');
                                                        });
                                                    }
                                                });
                                    });
                                }
                            });
                            break;
                    }
                }
            };
        }
);
