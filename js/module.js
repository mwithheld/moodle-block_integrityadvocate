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
        // On quizzes, disable the submit button and hide the questions until the IA modal is loaded.
        var is_quiz_attempt = (document.body.id === 'page-mod-quiz-attempt');
        var is_scorm_player = (document.body.id === 'page-mod-scorm-player');

        //@url https://stackoverflow.com/a/31350391
        function decodeEntities(encodedString) {
            var textArea = document.createElement('textarea');
            textArea.innerHTML = encodedString;
            return textArea.value;
        }

//        function endSessionWrapper() {
//            return new Promise((resolve) => {
//                window.IntegrityAdvocate.endSession((successResponse) => {
//                    resolve(successResponse);
//                });
//            });
//        }

//        function closeIASession() {
//            window.console.log('M.block_integrityadvocate.blockinit::SCORM Exit activity clicked - close the IA session');
//            return new Promise(function (resolve, reject) {
//                window.IntegrityAdvocate.endSession();
//                resolve(result);
//
//                getData(someValue, function (error, result) {
//                    if (error) {
//                        reject(error);
//                    } else {
//                        ;
//                    }
//                })
//            });
//        }

        // Disable quiz navigation until IA loaded.
        is_quiz_attempt && jQuery('.mod_quiz-next-nav').attr('disabled', 1);

        // The proctorjsurl is per-user and time-encoded unique, so there is no point in tryng to cache it.
        jQuery.getScript(decodeEntities(proctorjsurl))
                .done(function (script, textStatus) {
                    window.console.log('M.block_integrityadvocate.blockinit::Proctoring JS loaded');
                    jQuery(document).bind('IA_Ready', function (e) {
                        window.console.log('M.block_integrityadvocate.blockinit::IA_Ready event fired');
                        jQuery('#user-notifications').css({'background-image': 'none'}).height('auto');
                        switch (true) {
                            case is_quiz_attempt:
                                window.console.log('M.block_integrityadvocate.blockinit::Enable quiz nav and remove overlay');
                                jQuery('.mod_quiz-next-nav').removeAttr('disabled');
                                jQuery('#block_integrityadvocate_hidequiz').remove();
                                break;
                            case is_scorm_player:
                                var scorm_exit_button = jQuery('a.btn[href*="/course/view.php"][title="' + M.str.scorm.exitactivity + '"]');
                                scorm_exit_button.on('click', function (e) {
                                    window.console.log('M.block_integrityadvocate.blockinit::SCORM Exit activity clicked - close the IA session');
                                    window.IntegrityAdvocate.endSession(function () {
                                        window.console.log('M.block_integrityadvocate.blockinit::In the callback');
                                        window.location.href = scorm_exit_button.attr('href');
                                    });

                                    // The callback does not execute if the session is already closed.
                                    // Give it 2.5 seconds, then stop IA and do what the button was going to do.
                                    window.setTimeout(function () {
                                        window.IntegrityAdvocate.stop();
                                        window.top.IntegrityAdvocate.stop();
                                        window.location.href = scorm_exit_button.attr('href');
                                    }, 2500);

                                    e.preventDefault();
                                    return false;
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
                    window.console.log('M.block_integrityadvocate.blockinit::arguments', arguments);
                    window.console.log('M.block_integrityadvocate.blockinit::' + msg);
                    jQuery('#user-notifications').html('<div class="alert alert-danger alert-block fade in" role="alert" data-aria-autofocus="true">' + msg + '</div>');
                });
    }
};
