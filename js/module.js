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

    /**
     * Decode HTMLEntities.
     *
     * @ref https://stackoverflow.com/a/31350391
     * @param {type} encodedString
     * @returns {.document@call;createElement.value|textArea.value}
     */
    decodeEntities: function(encodedString) {
        var textArea = document.createElement('textarea');
        textArea.innerHTML = encodedString;
        return textArea.value;
    },

    /**
     * AJAX-load the proctor UI JS and run anything needed after.
     * The proctorjsurl is per-user and time-encoded unique, so there is no point in tryng to cache it.
     *
     * @returns null Nothing.
     */
    loadProctorUi: function(proctorjsurl) {
        if (/^https:\/\/ca\.integrityadvocateserver\.com\/participants\/integrity\?appid=.*/.test(proctorjsurl)) {
            window.console.log('M.block_integrityadvocate.loadProctorUi::Invalid input param');
        }
        if (typeof window.IntegrityAdvocate !== 'undefined') {
            window.console.log('M.block_integrityadvocate.loadProctorUi::IntegrityAdvocate is already loaded');
            return;
        }
        $.getScript(M.block_integrityadvocate.decodeEntities(proctorjsurl))
                .done(function() {
                    window.console.log('M.block_integrityadvocate.loadProctorUi::Proctoring JS loaded');
                    $(document).bind('IA_Ready', function() {
                        window.console.log('M.block_integrityadvocate.loadProctorUi::IA_Ready event fired');
                        M.block_integrityadvocate.eltUserNotifications.css({'background-image': 'none'}).height('auto');
                        switch (true) {
                            case M.block_integrityadvocate.isQuizAttempt:
                                window.console.log('M.block_integrityadvocate.loadProctorUi::On quizzes, disable the submit button and hide the questions until IA is ready');
                                $('.mod_quiz-next-nav').removeAttr('disabled');
                                $('#block_integrityadvocate_hidequiz').remove();
                                $('#responseform, #scormpage, div[role="main"]').show();
                                break;
                            case M.block_integrityadvocate.isScormPlayerSameWindow:
                                window.console.log('M.block_integrityadvocate.loadProctorUi::On SCORM samewindow, show the content and monitor for page close');
                                $('#responseform, #scormpage, div[role="main"]').show();
                                $('a.btn-secondary[title="' + M.util.get_string('scorm', 'exitactivity') + '"]').click(function() {
                                    window.console.log('Exiting the window - close the IA session');
                                    window.IntegrityAdvocate.endSession();
                                });
                                break;
                            case M.block_integrityadvocate.isScormEntryNewWindow:
                                window.console.log('M.block_integrityadvocate.loadProctorUi::On SCORM newwindow, show the Enter form and monitor for page close');
                                M.block_integrityadvocate.eltDivMain.find('*').show();
                                $('#responseform, #scormpage, div[role="main"]').show();
                                $('#block_integrityadvocate_loading').remove();
                                $(window).on('beforeunload', function() {
                                    window.console.log('Exiting the window - close the IA session');
                                    window.IntegrityAdvocate.endSession();
                                });
                                M.block_integrityadvocate.eltScormEnter.removeAttr('disabled').off('click.block_integrityadvocate').click().attr('disabled', 'disabled');
                                break;
                            default:
                                $('#responseform, #scormpage, div[role="main"]').show();
                        }
                    });
                })
                .fail(function(jqxhr, settings, exception) {
                    M.block_integrityadvocate.eltUserNotifications.css({'background-image': 'none'}).height('auto');
                    var msg = M.util.get_string('block_integrityadvocate', 'proctorjs_load_failed');
                    if (exception.toString() !== 'error') {
                        msg += "Error details:\n" + exception.toString();
                    }
                    window.console.log(arguments);
                    window.console.log(msg);
                    M.block_integrityadvocate.eltUserNotifications.html('<div class="alert alert-danger alert-block fade in" role="alert" data-aria-autofocus="true">' + msg + '</div>');
                    M.block_integrityadvocate.eltDivMain.show();
                    $('#block_integrityadvocate_loading').remove();
                });
    },

    blockinit: function(Y, proctorjsurl) {
        window.console.log('M.block_integrityadvocate.blockinit::Started with proctorjsurl=', proctorjsurl);

        M.block_integrityadvocate.isQuizAttempt = (document.body.id === 'page-mod-quiz-attempt');
        M.block_integrityadvocate.isScormPlayerSameWindow = (document.body.id === 'page-mod-scorm-player') && !M.mod_scormform;
        M.block_integrityadvocate.isScormEntryNewWindow = (document.body.id === 'page-mod-scorm-view') && typeof M.mod_scormform !== 'undefined';

        if (M.block_integrityadvocate.isScormEntryNewWindow || M.block_integrityadvocate.isScormPlayerSameWindow) {
            M.block_integrityadvocate.eltScormEnter = $('#scormviewform input[type="submit"]');
        }

        M.block_integrityadvocate.eltUserNotifications = $('#user-notifications');
        M.block_integrityadvocate.eltDivMain = $('div[role="main"]');

        $('head').append($('<link rel="stylesheet" type="text/css" />').attr('href', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'));

        M.block_integrityadvocate.isQuizAttempt && $('.mod_quiz-next-nav').attr('disabled', 1);

        // If we are on a "popup" SCORM entry page we want to trigger the IA proctoring only on button click.
        if (M.block_integrityadvocate.isScormEntryNewWindow) {
            window.console.log('This is a SCORM entry page');
            M.block_integrityadvocate.eltScormEnter.on('click.block_integrityadvocate', function(e) {
                $('#scormviewform input[type="submit"]').attr('disabled', 'disabled');
                e.preventDefault();

                M.block_integrityadvocate.eltDivMain.find('*').hide();
                M.block_integrityadvocate.eltUserNotifications.css('text-align', 'center').append('<i id="block_integrityadvocate_loading" class="fa fa-spinner fa-spin" style="font-size:72px"></i>');
                var offset = M.block_integrityadvocate.eltUserNotifications.offset();
                $('html, body').animate({
                    scrollTop: offset.top - 60,
                    scrollLeft: offset.left - 20
                });

                M.block_integrityadvocate.loadProctorUi(proctorjsurl);

                return false;
            });
        } else {
            M.block_integrityadvocate.loadProctorUi(proctorjsurl);
        }

    }
};
