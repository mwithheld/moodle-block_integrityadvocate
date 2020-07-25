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
    blockinit: function(Y, proctorjsurl) {
        window.console.log('M.block_integrityadvocate.blockinit::Started with proctorjsurl=', proctorjsurl);

        var isQuizAttempt = (document.body.id === 'page-mod-quiz-attempt');
        var isScormPlayerSameWindow = (document.body.id === 'page-mod-scorm-player') && !M.mod_scormform;
        var isScormEntryNewWindow = (document.body.id === 'page-mod-scorm-view') && typeof M.mod_scormform !== 'undefined';
        if (isScormEntryNewWindow || isScormPlayerSameWindow) {
            var eltScormEnter = $('#scormviewform input[type="submit"]');
        }
        var eltUserNotifications = $('#user-notifications');
        var eltDivMain = $('div[role="main"]');

        /**
         * Decode HTMLEntities.
         *
         * @ref https://stackoverflow.com/a/31350391
         * @param {type} encodedString
         * @returns {.document@call;createElement.value|textArea.value}
         */
        function decodeEntities(encodedString) {
            var textArea = document.createElement('textarea');
            textArea.innerHTML = encodedString;
            return textArea.value;
        }

        $('head').append($('<link rel="stylesheet" type="text/css" />').attr('href', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'));

        /**
         * AJAX-load the proctor UI JS and run anything needed after.
         * The proctorjsurl is per-user and time-encoded unique, so there is no point in tryng to cache it.
         *
         * @returns null Nothing.
         */
        function loadProctorUi() {
            if (typeof IntegrityAdvocate !== 'undefined') {
                return;
            }
            $.getScript(decodeEntities(proctorjsurl))
                    .done(function() {
                        window.console.log('M.block_integrityadvocate.blockinit::Proctoring JS loaded');
                        $(document).bind('IA_Ready', function() {
                            window.console.log('M.block_integrityadvocate.blockinit::IA_Ready event fired');
                            eltUserNotifications.css({'background-image': 'none'}).height('auto');
                            switch (true) {
                                case isQuizAttempt:
                                    window.console.log('M.block_integrityadvocate.blockinit::On quizzes, disable the submit button and hide the questions until IA is ready');
                                    $('.mod_quiz-next-nav').removeAttr('disabled');
                                    $('#block_integrityadvocate_hidequiz').remove();
                                    $('#responseform, #scormpage, div[role="main"]').show();
                                    break;
                                case isScormPlayerSameWindow:
                                    window.console.log('M.block_integrityadvocate.blockinit::On SCORM samewindow, show the content and monitor for page close');
                                    $('#responseform, #scormpage, div[role="main"]').show();
                                    $('a.btn-secondary[title="' + M.util.get_string('scorm', 'exitactivity') + '"]').click(function() {
                                        window.console.log('Exiting the window - close the IA session');
                                        window.IntegrityAdvocate.endSession();
                                    });
                                    break;
                                case isScormEntryNewWindow:
                                    window.console.log('M.block_integrityadvocate.blockinit::On SCORM newwindow, show the Enter form and monitor for page close');
                                    eltDivMain.find('*').show();
                                    $('#responseform, #scormpage, div[role="main"]').show();
                                    $('#block_integrityadvocate_loading').remove();
                                    $(window).on('beforeunload', function() {
                                        window.console.log('Exiting the window - close the IA session');
                                        window.IntegrityAdvocate.endSession();
                                    });
                                    eltScormEnter.removeAttr('disabled').off('click.block_integrityadvocate').click().attr('disabled', 'disabled');
                                    break;
                                default:
                                    $('#responseform, #scormpage, div[role="main"]').show();
                            }
                        });
                    })
                    .fail(function(jqxhr, settings, exception) {
                        eltUserNotifications.css({'background-image': 'none'}).height('auto');
                        var msg = M.util.get_string('block_integrityadvocate', 'proctorjs_load_failed');
                        if (exception.toString() !== 'error') {
                            msg += "Error details:\n" + exception.toString();
                        }
                        window.console.log(arguments);
                        window.console.log(msg);
                        eltUserNotifications.html('<div class="alert alert-danger alert-block fade in" role="alert" data-aria-autofocus="true">' + msg + '</div>');
                        eltDivMain.show();
                        $('#block_integrityadvocate_loading').remove();
                    });
        }

        isQuizAttempt && $('.mod_quiz-next-nav').attr('disabled', 1);

        // If we are on a "popup" SCORM entry page we want to trigger the IA proctoring only on button click.
        if (isScormEntryNewWindow) {
            window.console.log('This is a SCORM entry page');
            eltScormEnter.on('click.block_integrityadvocate', function(e) {
                $('#scormviewform input[type="submit"]').attr('disabled', 'disabled');
                e.preventDefault();

                eltDivMain.find('*').hide();
                eltUserNotifications.css('text-align', 'center').append('<i id="block_integrityadvocate_loading" class="fa fa-spinner fa-spin" style="font-size:72px"></i>');
                var offset = eltUserNotifications.offset();
                $('html, body').animate({
                    scrollTop: offset.top - 60,
                    scrollLeft: offset.left - 20
                });

                loadProctorUi();

                return false;
            });
        } else {
            loadProctorUi();
        }

    }
};
