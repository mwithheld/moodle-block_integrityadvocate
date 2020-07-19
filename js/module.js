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
        var is_scorm_player_samewindow = (document.body.id === 'page-mod-scorm-player') && !M.mod_scormform;
        var is_scorm_entry_newwindow = (document.body.id === 'page-mod-scorm-view') && typeof M.mod_scormform !== 'undefined';
        if (is_scorm_entry_newwindow || is_scorm_player_samewindow) {
            var elt_scorm_enter = jQuery('#scormviewform input[type="submit"]');
        }
        var elt_usernotifications = jQuery('#user-notifications');
        var elt_div_main = jQuery('div[role="main"]');

        //@url https://stackoverflow.com/a/31350391
        function decodeEntities(encodedString) {
            var textArea = document.createElement('textarea');
            textArea.innerHTML = encodedString;
            return textArea.value;
        }

        jQuery('head').append(jQuery('<link rel="stylesheet" type="text/css" />').attr('href', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'));

        // The proctorjsurl is per-user and time-encoded unique, so there is no point in tryng to cache it.
        function loadproctorui() {
            if (typeof IntegrityAdvocate !== 'undefined') {
                return;
            }
            jQuery.getScript(decodeEntities(proctorjsurl))
                    .done(function (script, textStatus) {
                        window.console.log('M.block_integrityadvocate.blockinit::Proctoring JS loaded');
                        jQuery(document).bind('IA_Ready', function (e) {
                            window.console.log('M.block_integrityadvocate.blockinit::IA_Ready event fired');
                            elt_usernotifications.css({'background-image': 'none'}).height('auto');
                            switch (true) {
                                case is_quiz_attempt:
                                    window.console.log('M.block_integrityadvocate.blockinit::On quizzes, disable the submit button and hide the questions until IA is ready');
                                    jQuery('.mod_quiz-next-nav').removeAttr('disabled');
                                    jQuery('#block_integrityadvocate_hidequiz').remove();
                                    jQuery('#responseform, #scormpage, div[role="main"]').show();
                                    break;
                                case is_scorm_player_samewindow:
                                    window.console.log('M.block_integrityadvocate.blockinit::On SCORM samewindow, show the content and monitor for page close');
                                    jQuery('#responseform, #scormpage, div[role="main"]').show();
                                    jQuery('a.btn-secondary[title="' + M.str.scorm.exitactivity + '"]').click(function () {
                                        window.console.log('Exiting the window - close the IA session');
                                        window.IntegrityAdvocate.endSession();
                                    });
                                    break;
                                case is_scorm_entry_newwindow:
                                    window.console.log('M.block_integrityadvocate.blockinit::On SCORM newwindow, show the Enter form and monitor for page close');
                                    elt_div_main.find('*').show();
                                    jQuery('#responseform, #scormpage, div[role="main"]').show();
                                    jQuery('#block_integrityadvocate_loading').remove();
                                    jQuery(window).on('beforeunload', function () {
                                        window.console.log('Exiting the window - close the IA session');
                                        window.IntegrityAdvocate.endSession();
                                    });
                                    elt_scorm_enter.removeAttr('disabled').off('click.block_integrityadvocate').click().attr('disabled', 'disabled');
                                    break;
                                default:
                                    jQuery('#responseform, #scormpage, div[role="main"]').show();
                            }
                        });
                    })
                    .fail(function (jqxhr, settings, exception) {
                        elt_usernotifications.css({'background-image': 'none'}).height('auto');
                        var msg = M.str.block_integrityadvocate.proctorjs_load_failed;
                        if (exception.toString() !== 'error') {
                            msg += "Error details:\n" + exception.toString();
                        }
                        window.console.log(arguments);
                        window.console.log(msg);
                        elt_usernotifications.html('<div class="alert alert-danger alert-block fade in" role="alert" data-aria-autofocus="true">' + msg + '</div>');
                        elt_div_main.show();
                        jQuery('#block_integrityadvocate_loading').remove();
                    });
        }

        is_quiz_attempt && jQuery('.mod_quiz-next-nav').attr('disabled', 1);

        // If we are on a "popup" SCORM entry page we want to trigger the IA proctoring only on button click.
        if (is_scorm_entry_newwindow) {
            window.console.log('This is a SCORM entry page');
            elt_scorm_enter.on('click.block_integrityadvocate', function (e) {
                jQuery('#scormviewform input[type="submit"]').attr('disabled', 'disabled');
                e.preventDefault();

                elt_div_main.find('*').hide();
                elt_usernotifications.css('text-align', 'center').append('<i id="block_integrityadvocate_loading" class="fa fa-spinner fa-spin" style="font-size:72px"></i>');
                var offset = elt_usernotifications.offset();
                $('html, body').animate({
                    scrollTop: offset.top - 60,
                    scrollLeft: offset.left - 20
                });

                loadproctorui();

                return false;
            });
        } else {
            loadproctorui();
        }

    }
};
