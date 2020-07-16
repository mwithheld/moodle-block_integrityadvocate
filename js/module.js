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
    blockinit: function (Y, proctorjsurl) {
        window.console.log('M.block_integrityadvocate.blockinit::Started with proctorjsurl=', proctorjsurl);

        var is_quiz_attempt = (document.body.id === 'page-mod-quiz-attempt');
        var is_scorm_entry = (document.body.id === 'page-mod-scorm-view');

        //@url https://stackoverflow.com/a/31350391
        function decodeEntities(encodedString) {
            var textArea = document.createElement('textarea');
            textArea.innerHTML = encodedString;
            return textArea.value;
        }


        // The proctorjsurl is per-user and time-encoded unique, so there is no point in tryng to cache it.
        function loadproctorui() {
            jQuery.getScript(decodeEntities(proctorjsurl))
                    .done(function (script, textStatus) {
                        window.console.log('M.block_integrityadvocate.blockinit::Proctoring JS loaded');
                        jQuery(document).bind('IA_Ready', function (e) {
                            window.console.log('M.block_integrityadvocate.blockinit::IA_Ready event fired');
                            jQuery('#user-notifications').css({'background-image': 'none'}).height('auto');
                            switch (true) {
                                case (is_quiz_attempt):
                                    // On quizzes, disable the submit button and hide the questions until IA is ready.
                                    jQuery('.mod_quiz-next-nav').removeAttr('disabled');
                                    jQuery('#block_integrityadvocate_hidequiz').remove();
                                    jQuery('#responseform, #scormpage, div[role="main"]').show()
                                    break;
                                case (is_scorm_entry) :
//                                    jQuery('#scormviewform').attr('action', is_scorm_entry);
//                                    jQuery('#scormviewform').submit();
                                    $(window).on('beforeunload', function () {
                                        window.console.log('Exiting the window - close the IA session');
                                        window.IntegrityAdvocate.endSession();
                                    });
                                    break;
                            }
                        });
                    })
                    .fail(function (jqxhr, settings, exception) {
                        jQuery('#user-notifications').css({'background-image': 'none'}).height('auto');
                        var msg = M.str.block_integrityadvocate.proctorjs_load_failed;
                        if (exception.toString() !== 'error') {
                            msg += "Error details:\n" + exception.toString();
                        }
                        window.console.log(arguments);
                        window.console.log(msg);
                        jQuery('#user-notifications').html('<div class="alert alert-danger alert-block fade in" role="alert" data-aria-autofocus="true">' + msg + '</div>');
                    });
        }

        is_quiz_attempt && jQuery('.mod_quiz-next-nav').attr('disabled', 1);

        // Load the proctoring UI except if we are on the SCORM entry page, where we want to trigger it on button click.
//        if (is_scorm_entry) {
//            window.console.log('This is a SCORM entry page');
//            $(window).on('beforeunload', function () {
//                window.IntegrityAdvocate.endSession();
//            });
//            is_scorm_entry = jQuery('#scormviewform').attr('action');
//            jQuery('#scormviewform').attr('action', 'return false;')
//            jQuery('#scormviewform').submit(function (e) {
//                window.console.log('SCORM Enter button clicked');
//                loadproctorui();
//                e.preventDefault();
//                return false;
//            });
//        } else {
        loadproctorui();
//        }

    }
};
