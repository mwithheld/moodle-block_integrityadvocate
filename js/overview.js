// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JS for when the block is shown.
 *
 * @package    block_integrityadvocate
 * @copyright  IntegrityAdvocate.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

M.block_integrityadvocate = {
    overviewinit: function () {
        var fadetime = 300;
        // Provide convenient access to the form components.
        var frm;
        // Prefix used on most form items.
        var prefix = 'block_integrityadvocate_override';

        var preventdefault = function (e) {
            if (typeof e !== 'undefined') {
                e.preventDefault();
            }
            return false;
        };

        var setup = function () {
            // Make easy-to-read refs to the form elements.
            frm = $('#' + prefix + '_form');
            frm.elt_status = $('#' + prefix + '_status_select');
            frm.elt_reason = $('#' + prefix + '_reason');
            frm.userinputs = [frm.elt_status, frm.elt_reason];
            frm.elt_targetuserid = $('#' + prefix + '_targetuserid');
            frm.elt_overrideuserid = $('#' + prefix + '_overrideuserid');
            frm.elt_edit = $('#' + prefix + '_edit');
            frm.elt_save = $('#' + prefix + '_save');
            frm.elt_loading = $('#' + prefix + '_loading');
            frm.elt_cancel = $('#' + prefix + '_cancel');

            frm.elt_reason
                    .attr('placeholder', M.str.block_integrityadvocate.override_reason_label)
                    .attr('pattern', '^[a-zA-Z0-9\ .,_-]{0,32}')
                    .on('keypress.' + prefix + '_disable', function (e) {
                        if (e.which == 13) {
                            frm.elt_save.click();
                            e.preventDefault();
                            return false;
                        }
                    });

            frm.elt_loading.hide();
            save_set_status(false);
            frm[0].reset();

            frm.elt_save.on('click', function (e) {
                if (validate_all()) {
                    save_click();
                }
                e.preventDefault();
                return false;
            });
            frm.elt_edit.on('click', function (e) {
                show_overrideui();
                e.preventDefault();
                return false;
            });
            frm.elt_cancel.on('click', function (e) {
                cancel_click();
                e.preventDefault();
                return false;
            });
            $(frm.userinputs).each(function (_, userinput) {
                userinput.on('change keyup blur', function (e) {
                    save_set_status(validate_all());
                    e.preventDefault();
                    return false;
                });
            });
        };

        var cancel_click = function () {
            frm[0].reset();
            hide_overrideui();
        };

        var save_click = function () {
            disable_ui();

            var url = new URL(window.location.href);

            require(['core/ajax', 'core/notification'], function (ajax, notification) {
                ajax.call([{
                        methodname: 'block_integrityadvocate_set_override',
                        args: {status: frm.elt_status.val(), reason: frm.elt_reason.val(), targetuserid: frm.elt_targetuserid.val(), overrideuserid: frm.elt_overrideuserid.val(), blockinstanceid: url.searchParams.get('instanceid')},
                        done: function (context) {
                            // Set true to force reload from server not cache.  This is said to be deprecated but since we might have old browsers we'll do it.
                            window.location.reload(true);
                        },
                        fail: notification.exception,
                        always: function (context) {
                            enable_ui();
                            cancel_click();
                        }
                    }]);
            });
        };

        var validate_all = function () {
            // We explicity want to run through both validators, so don't just AND them.
            var isvalid_reason = validate_reason();
            var isvalid_status = validate_status();

            return isvalid_reason && isvalid_status;
        };

        var validate_status = function () {
            var elt = frm.elt_status;
            var val = elt.val();

            // Only values 0 and 3 are acceptable ATM.
            return elt[0].checkValidity() || val === 0 || val === 3;
        };

        var validate_reason = function () {
            var elt = frm.elt_reason;
            var val = elt.val();

            // Clear the custom validity message b/c having it there cause checkValidity() to return false.
            elt[0].setCustomValidity('');

            if (val === '' || elt[0].checkValidity()) {
                return true;
            } else {
                elt[0].setCustomValidity(M.str.block_integrityadvocate.override_reason_invalid);
                return false;
            }
        };

        var save_set_status = function (enabled) {
            if (enabled) {
                save_enable();
            } else {
                save_disable();
            }
        };

        var save_disable = function () {
            frm.elt_save.css('color', 'grey').on('click.' + prefix + '_disable', preventdefault);
        };

        var save_enable = function () {
            frm.elt_save.css('color', '').off('click.' + prefix + '_disable');
        };

        var show_overrideui = function () {
            frm.elt_edit.hide();
            frm.show();
            // Make the save icon blink a bit for visibility.
            frm.elt_save.fadeOut(fadetime).fadeIn(fadetime).fadeOut(fadetime).fadeIn(fadetime);
            validate_all();
        };

        var hide_overrideui = function () {
            frm.hide();
            frm.elt_edit.fadeIn(fadetime);
        };

        var disable_ui = function () {
            frm.elt_cancel.hide();
            frm.elt_save.hide();
            frm.elt_loading.show();
            $(frm.userinputs).each(function (_, userinput) {
                userinput.attr('disabled', 'disabled');
            });
        };

        var enable_ui = function () {
            frm.elt_cancel.show();
            frm.elt_save.show();
            frm.elt_loading.hide();
            $(frm.userinputs).each(function (_, userinput) {
                userinput.removeAttr('disabled');
            });
        };

        setup();
    }
};
