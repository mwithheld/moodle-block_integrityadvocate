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
if (typeof M.block_integrityadvocate === 'undefinedd') {
    M.block_integrityadvocate_data = {};
}
M.block_integrityadvocate = {
    ...M.block_integrityadvocate_data,
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
    sessionOpen: function() {
        var debug = true;
        debug && window.console.log('M.block_integrityadvocate.sessionOpen::Started');
        var self = M.block_integrityadvocate;

        require(['core/ajax'], function(ajax) {
            ajax.call([{
                    methodname: 'block_integrityadvocate_session_open',
                    args: {
                        appid: self.appid,
                        courseid: self.courseid,
                        moduleid: self.activityid,
                        userid: self.participantidentifier
                    },
                    done: function() {
                        debug && window.console.log('M.block_integrityadvocate.sessionOpen::ajax.done');
                    },
                    fail: function(xhr_unused, textStatus, errorThrown) {
                        debug && window.console.log('M.block_integrityadvocate.sessionOpen::ajax.fail');
                        console.log('textStatus', textStatus);
                        console.log('errorThrown', errorThrown);
                        alert(M.util.get_string('unknownerror', 'moodle') + ' M.block_integrityadvocate.sessionOpen::ajax.fail');
                    }
                }]);
        });

        debug && window.console.log('M.block_integrityadvocate.sessionOpen::Done');
    },
    sessionClose: function(callback) {
        var debug = true;
        debug && window.console.log('M.block_integrityadvocate.sessionClose::Started');
        var self = M.block_integrityadvocate;

        require(['core/ajax'], function(ajax) {
            ajax.call([{
                    methodname: 'block_integrityadvocate_session_close',
                    args: {
                        appid: self.appid,
                        courseid: self.courseid,
                        moduleid: self.activityid,
                        userid: self.participantidentifier
                    },
                    done: function() {
                        debug && window.console.log('M.block_integrityadvocate.sessionClose::ajax.done');
                        typeof callback === 'function' && callback();
                    },
                    fail: function(xhr_unused, textStatus, errorThrown) {
                        debug && window.console.log('M.block_integrityadvocate.sessionClose::ajax.fail');
                        console.log('textStatus', textStatus);
                        console.log('errorThrown', errorThrown);
                        window.IntegrityAdvocate.endSession();
                        alert(M.util.get_string('unknownerror', 'moodle') + ' M.block_integrityadvocate.sessionClose::ajax.fail');
                    }
                }]);
        });

        debug && window.console.log('M.block_integrityadvocate.sessionClose::Done');
    },
    /**
     * Stuff to do when the proctor UI is loaded.
     * Hide the loading gif and show the main content.
     *
     * @returns nothing.
     */
    proctorUILoaded: function() {
        var debug = true;
        debug && window.console.log('M.block_integrityadvocate.proctorUILoaded::Started');
        var self = M.block_integrityadvocate;
        self.eltUserNotifications.css({'background-image': 'none'}).height('auto');
        var eltMainContent = $('#responseform, #scormpage, div[role="main"]');
        switch (true) {
            case self.isQuizAttempt:
                debug && window.console.log('M.block_integrityadvocate.proctorUILoaded::On quizzes, disable the submit button and hide the questions until IA is ready');
                $('.mod_quiz-next-nav').removeAttr('disabled');
                $('#block_integrityadvocate_hidequiz').remove();
                eltMainContent.show();
                break;
            case self.isScormPlayerSameWindow:
                debug && window.console.log('M.block_integrityadvocate.proctorUILoaded::On SCORM samewindow, show the content and monitor for page close');
                eltMainContent.show();

                var elt = $('a.btn-secondary[title="' + M.util.get_string('exitactivity', 'scorm') + '"]');
                elt.on('click.block_integrityadvocate', function(e) {
                    debug && window.console.log('M.block_integrityadvocate.exitactivity.on(click)::started');
                    self.sessionClose(function() {
                        debug && window.console.log('M.block_integrityadvocate.exitactivity.promise.done::started');
                        elt.off('click.block_integrityadvocate')
                        elt[0].click();
                    });
                    e.preventDefault();
                    return false;
                });
                break;
            case self.isScormEntryNewWindow:
                debug && window.console.log('M.block_integrityadvocate.proctorUILoaded::On SCORM newwindow, show the Enter form and monitor for page close');
                self.eltDivMain.find('*').show();
                eltMainContent.show();
                $('#block_integrityadvocate_loading').remove();
                $(window).on('beforeunload', function() {
                    debug && window.console.log('M.block_integrityadvocate.proctorUILoaded::Exiting the window - close the IA session');
                    self.sessionClose();
                });
                self.eltScormEnter.removeAttr('disabled').off('click.block_integrityadvocate').click().attr('disabled', 'disabled');
                break;
            default:
                debug && window.console.log('M.block_integrityadvocate.proctorUILoaded::This is the default page handler');
                eltMainContent.show();
        }
    },
    /**
     * AJAX-load the proctor UI JS and run anything needed after.
     * The proctorjsurl is per-user and time-encoded unique, so there is no point in tryng to cache it.
     *
     * @returns null Nothing.
     */
    loadProctorUi: function(proctorjsurl) {
        var debug = true;
        var self = M.block_integrityadvocate;
        debug && window.console.log('M.block_integrityadvocate.loadProctorUi::Started with proctorjsurl=', proctorjsurl);
        if (!/^https:\/\/ca\.integrityadvocateserver\.com\/participants\/integrity\?appid=.*/.test(proctorjsurl)) {
            window.console.error('M.block_integrityadvocate.loadProctorUi::Invalid input param');
            return;
        }
        debug && window.console.log('M.block_integrityadvocate.loadProctorUi::About to check for window.IntegrityAdvocate=', window.IntegrityAdvocate);
        // To prevent double-loading of the IA logic, check if IA is already loaded in this window.
        if (typeof window.IntegrityAdvocate !== 'undefined') {
            window.console.log('M.block_integrityadvocate.loadProctorUi::IntegrityAdvocate is already loaded');
            // Hide the loading gif and show the main content.
            self.proctorUILoaded();
            return;
        }
        $.getScript(self.decodeEntities(proctorjsurl))
                .done(function() {
                    debug && window.console.log('M.block_integrityadvocate.loadProctorUi::Proctoring JS loaded');
                    $(document).bind('IA_Ready', function() {
                        // Remember that we have started a session so we only close it once.
                        self.sessionOpen();

                        // Hide the loading gif and show the main content.
                        self.proctorUILoaded();
                    });
                })
                .fail(function(jqxhr, settings, exception) {
                    // Hide the loading gif.
                    $('#block_integrityadvocate_loading').remove();
                    self.eltUserNotifications.css({'background-image': 'none'}).height('auto');
                    // Show the main content.
                    self.eltDivMain.show();
                    // Dump out some info about what went wrong.
                    var msg = M.util.get_string('proctorjs_load_failed', 'block_integrityadvocate');
                    if (exception.toString() !== 'error') {
                        msg += "Error details:\n" + exception.toString();
                    }
                    debug && window.console.log('M.block_integrityadvocate.proctorUILoaded::' + msg);
                    debug && window.console.log(arguments);
                    self.eltUserNotifications.html('<div class="alert alert-danger alert-block fade in" role="alert" data-aria-autofocus="true">' + msg + '</div>');
                });
    },
    blockinit: function(Y, proctorjsurl) {
        var debug = true;
        var self = M.block_integrityadvocate;
        debug && window.console.log('M.block_integrityadvocate.blockinit::Started with proctorjsurl=', proctorjsurl);

        // Register input vars for re-use.
        var url = new URL(proctorjsurl);
        var params = new URLSearchParams(url.search.replace(/\&amp;/g, '&'));
        debug && window.console.log('M.block_integrityadvocate.blockinit::Parsed proctorjsurl=', url);
        params.forEach(function(value, key) {
            self[decodeURIComponent(key)] = decodeURIComponent(value);
        });

        // Register derived vars for re-use.
        self.isQuizAttempt = (document.body.id === 'page-mod-quiz-attempt');
        self.isScormPlayerSameWindow = (document.body.id === 'page-mod-scorm-player') && !M.mod_scormform;
        self.isScormEntryNewWindow = (document.body.id === 'page-mod-scorm-view') && typeof M.mod_scormform !== 'undefined';
        if (self.isScormEntryNewWindow || self.isScormPlayerSameWindow) {
            self.eltScormEnter = $('#scormviewform input[type="submit"]');
        }
        self.eltUserNotifications = $('#user-notifications');
        self.eltDivMain = $('div[role="main"]');

        // Handlers for different kinds of pages - this is for any required setup before the IA JS is loaded.
        switch (true) {
            case (self.isQuizAttempt):
                debug && window.console.log('M.block_integrityadvocate.blockinit::This is a quiz attempt page');
                // Disables the Next button until IA JS is loaded
                $('.mod_quiz-next-nav').attr('disabled', 1);
                self.loadProctorUi(proctorjsurl);
                break;
            case (self.isScormEntryNewWindow):
                debug && window.console.log('M.block_integrityadvocate.blockinit::This is a SCORM new "popup" entry page');
                // Trigger the IA proctoring only on button click.
                self.eltScormEnter.on('click.block_integrityadvocate', function(e) {
                    $('#scormviewform input[type="submit"]').attr('disabled', 'disabled');
                    e.preventDefault();

                    // Hide the SCORM content until the IA JS is loaded.
                    self.eltDivMain.find('*').hide();
                    self.eltUserNotifications.css('text-align', 'center').append('<i id="block_integrityadvocate_loading" class="fa fa-spinner fa-spin" style="font-size:72px"></i>');

                    // Fix display of the loading gif.
                    var offset = self.eltUserNotifications.offset();
                    $('html, body').animate({
                        scrollTop: offset.top - 60,
                        scrollLeft: offset.left - 20
                    });
                    self.loadProctorUi(proctorjsurl);
                    return false;
                });
                break;
            default:
                debug && window.console.log('M.block_integrityadvocate.blockinit::This is the default page handler');
                self.loadProctorUi(proctorjsurl);
        }

    }
};
