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

        var preventdefault = function (e) {
            if (typeof e !== 'undefined') {
                e.preventDefault();
            }
            return false;
        };

        var setup = function () {
            var debug = false;
            debug && window.console.log('setup::Started');

            $('#block_integrityadvocate_override_reason')
                    .attr('placeholder', M.str.block_integrityadvocate.override_reason_label)
                    .attr('pattern', '^[a-zA-Z0-9._-]{0,32}');
//            $('#block_integrityadvocate_override_reason, #block_integrityadvocate_override_status_select').on('change submit keyup', function (e) {
//                if (!validate_all()) {
//                    preventdefault(e);
//                    save_set_status(false);
//                }
//            });
            $('.block_integrityadvocate_override_save').click(save_click());
        };

        var save_click = function () {
            require(['core/ajax', 'core/notification'], function (ajax, notification) {
                ajax.call([{
                        methodname: 'block_integrityadvocate_set_override',
                        args: {status: 0, reason: 'some bogus reason', targetuserid: 999, overrideuserid: 111, cmid: -1},
                        done: function (context) {
                            window.console.log('override save done');
                        },
                        fail: notification.exception
                    }]);
            });
        };

        var validate_all = function () {
            // We explicity want to run through both validators.
            var isvalid_reason = validate_reason();
            window.console.log('validate_all::isvalid_reason=', isvalid_reason);
            var isvalid_status = validate_status();
            window.console.log('validate_all::isvalid_status=', isvalid_status);

            return isvalid_reason && isvalid_status;
        };

        var validate_status = function () {
            var debug = false;
            var elt = $('#block_integrityadvocate_override_status_select');
            var val = elt.val();
            debug && window.console.log('validate_status::Started with val=', val);

            // Only values 0 and 3 are acceptable
            return elt[0].checkValidity() || val === 0 || val === 3;
        };

        var validate_reason = function () {
            var debug = true;
            var elt = $('#block_integrityadvocate_override_reason');
            var val = elt.val();
            debug && window.console.log('validate_reason::Started with val=', val);

            if (val === '' || elt[0].checkValidity()) {
                debug && window.console.log('validate_reason::reason is valid');
                elt[0].setCustomValidity('');
                return true;
            } else {
                debug && window.console.log('validate_reason::reason is invalid');
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
            var debug = true;
            debug && window.console.log('save_disable::Started');

            $('.block_integrityadvocate_override_save').css('color', 'grey')
                    .on('click.block_integrityadvocate_override_disable', preventdefault);
        };

        var save_enable = function () {
            $('.block_integrityadvocate_override_save')
                    .css('color', '')
                    .off('click.block_integrityadvocate_override_disable');
        };

        var show_overrideui = function () {
            $('.block_integrityadvocate_override_edit').hide();
            $('#block_integrityadvocate_override_form').show();
            // Make the save icon blink for visibility.
            $('.block_integrityadvocate_override_save_span').fadeOut(fadetime).fadeIn(fadetime).fadeOut(fadetime).fadeIn(fadetime).fadeOut(fadetime).fadeIn(fadetime);
            validate_all();
        };

        var hide_overrideui = function () {
            $('#block_integrityadvocate_override_form').hide();
            $('.block_integrityadvocate_override_edit').fadeIn(fadetime);
        };

        setup();

        $('.block_integrityadvocate_override_edit').click(function () {
            show_overrideui();
        });

        $('#block_integrityadvocate_override_cancel').click(function () {
            hide_overrideui();
        });
    }
};
