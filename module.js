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
    blockinit: function () {
        // Disabled on purpose: window.console.log('IA moodle js init started');.
        // On quizzes,disable the submit button and hide the questions until the IA modal is loaded.
        if (document.body.id === 'page-mod-quiz-attempt') {
            jQuery('.mod_quiz-next-nav').attr('disabled', 1);
            window.console.log('Disabled quiz submission until IA loads');
            $.when($('integrityadvocate-activityblocker')).then((self) => {
                jQuery('.mod_quiz-next-nav').attr('disabled', 0).removeAttr('disabled');
                jQuery('#block_integrityadvocate_hidequiz').remove();
                window.console.log('Enabled quiz submission now that IA is loaded');
            });
        }
    }
};
