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

        var showoverrideui = function () {
            window.console.log('override_edit_click::Started');
            $('.block_integrityadvocate_override_edit, .block_integrityadvocate_status_val').hide();
            $('.block_integrityadvocate_override_select, .block_integrityadvocate_override_cancel_span').fadeIn(fadetime);
            $('.block_integrityadvocate_override_save_span').show().fadeOut(fadetime).fadeIn(fadetime).fadeOut(fadetime).fadeIn(fadetime).fadeOut(fadetime).fadeIn(fadetime);
        }

        $('.block_integrityadvocate_override_edit').click(function () {
            showoverrideui();
        });
    }
};
