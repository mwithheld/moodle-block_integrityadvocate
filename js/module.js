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
 * JS for when the block is shown.  Assumes JQuery is included in the code that pulls in this JS.
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
    decodeEntities: function (encodedString) {
        var textArea = document.createElement('textarea');
        textArea.innerHTML = encodedString;
        return textArea.value;
    },
    /**
     * Open an IA proctoring session.
     *
     * @returns {null} Nothing.
     */
    sessionOpen: function () {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.sessionOpen';
        debug && window.console.log(fxn + '::Started');
        var self = M.block_integrityadvocate;

        require(['core/ajax'], function (ajax) {
            ajax.call([{
                methodname: 'block_integrityadvocate_session_open',
                args: {
                    appid: self.appid,
                    courseid: self.courseid,
                    moduleid: self.activityid,
                    userid: self.participantidentifier
                },
                done: function () {
                    debug && window.console.log(fxn + '::ajax.done');
                },
                fail: function (xhr_unused, textStatus, errorThrown) {
                    debug && window.console.log(fxn + '::ajax.fail');
                    window.console.log('textStatus', textStatus);
                    window.console.log('errorThrown', errorThrown);
                    alert(M.util.get_string('unknownerror', 'moodle') + ' ' + fxn + '::ajax.fail');
                }
            }]);
        });

        debug && window.console.log(fxn + '::Done');
    },
    /**
     * Close an IA proctoring session.
     *
     * @param {function} callback Function to call when done.
     * @returns {null} Nothing.
     */
    sessionClose: function (callback) {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.sessionClose';
        debug && window.console.log(fxn + '::Started');
        var self = M.block_integrityadvocate;

        require(['core/ajax'], function (ajax) {
            ajax.call([{
                methodname: 'block_integrityadvocate_session_close',
                args: {
                    appid: self.appid,
                    courseid: self.courseid,
                    moduleid: self.activityid,
                    userid: self.participantidentifier
                },
                done: function () {
                    debug && window.console.log(fxn + '::ajax.done');
                    typeof callback === 'function' && callback();
                },
                fail: function (xhr_unused, textStatus, errorThrown) {
                    debug && window.console.log(fxn + '::ajax.fail');
                    window.console.log('textStatus', textStatus);
                    window.console.log('errorThrown', errorThrown);
                    window.IntegrityAdvocate.endSession();
                    alert(M.util.get_string('unknownerror', 'moodle') + ' M.block_integrityadvocate.sessionClose::ajax.fail');
                }
            }]);
        });

        debug && window.console.log(fxn + '::Done');
    },
    /**
     * Stuff to do when the proctor UI is loaded.
     * Hide the loading gif and show the main content.
     *
     * @returns {null} Nothing.
     */
    proctorUILoaded: function () {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.proctorUILoaded';
        debug && window.console.log(fxn + '::Started');
        var self = M.block_integrityadvocate;
        self.eltUserNotifications.css({ 'background-image': 'none' }).height('auto');
        var eltMainContent = $('#responseform, #scormpage, div[role="main"]');
        switch (true) {
            case self.isQuizAttempt:
                debug && window.console.log(fxn + '::On quizzes, disable the submit button and hide the questions until IA is ready', self.eltQuizNextButtonSet);
                self.eltQuizNextButtonSet.removeAttr('disabled').off('click.block_integrityadvocate.disable');
                $('#block_integrityadvocate_hidequiz').remove();
                eltMainContent.show();
                break;
            case self.isScormPlayerSameWindow:
                debug && window.console.log(fxn + '::On SCORM samewindow, show the content and monitor for page close');
                eltMainContent.show();

                var elt = $('a.btn-secondary[title="' + M.util.get_string('exitactivity', 'scorm') + '"]');
                elt.on('click.block_integrityadvocate', function (e) {
                    debug && window.console.log('M.block_integrityadvocate.exitactivity.on(click)::started');
                    self.sessionClose(function () {
                        debug && window.console.log('M.block_integrityadvocate.exitactivity.promise.done::started');
                        elt.off('click.block_integrityadvocate');
                        elt[0].click();
                    });
                    e.preventDefault();
                    return false;
                });
                break;
            case self.isScormEntryNewWindow:
                debug && window.console.log(fxn + '::On SCORM newwindow, show the Enter form and monitor for page close');
                self.eltDivMain.find('*').show();
                eltMainContent.show();
                $('#block_integrityadvocate_loading').remove();
                $(window).on('beforeunload', function () {
                    debug && window.console.log(fxn + '::Exiting the window - close the IA session');
                    self.sessionClose();
                });
                self.eltScormEnter.removeAttr('disabled').off('click.block_integrityadvocate').click().attr('disabled', 'disabled');
                break;
            default:
                debug && window.console.log(fxn + '::This is the default page handler');
                eltMainContent.show();
        }
    },
    /**
     * AJAX-load the proctor UI JS and run anything needed after.
     * The proctorjsurl is per-user and time-encoded unique, so there is no point in tryng to cache it.
     *
     * @param {string} proctorjsurl URL to the IA proctor JS.
     * @returns {null} Nothing.
     */
    loadProctorUi: function (proctorjsurl) {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.blockinit';
        debug && window.console.log(fxn + '::Started with proctorjsurl=', proctorjsurl);
        var self = M.block_integrityadvocate;

        if (!self.isHttpUrl(proctorjsurl)) {
            window.console.error(fxn + '::Invalid input param proctorjsurl=', proctorjsurl);
            return;
        }

        debug && window.console.log(fxn + '::About to check for window.IntegrityAdvocate=', window.IntegrityAdvocate);
        // To prevent double-loading of the IA logic, check if IA is already loaded in this window.
        if (typeof window.IntegrityAdvocate !== 'undefined') {
            window.console.log(fxn + '::IntegrityAdvocate is already loaded');
            // Hide the loading gif and show the main content.
            self.proctorUILoaded();
            return;
        }

        debug && window.console.log(fxn + '::About to getScript()');
        $.getScript(self.decodeEntities(proctorjsurl))
            .done(function () {
                self.getProctorJsDone();
            })
            .fail(function (jqxhr, settings, exception) {
                // Hide the loading gif.
                $('#block_integrityadvocate_loading').remove();
                self.eltUserNotifications.css({ 'background-image': 'none' }).height('auto');
                // Show the main content.
                self.eltDivMain.show();
                // Dump out some info about what went wrong.
                var msg = M.util.get_string('proctorjs_load_failed', 'block_integrityadvocate');
                if (exception.toString() !== 'error') {
                    msg += "Error details:\n" + exception.toString();
                }

                window.console.log(fxn + '.getScript.fail::', msg, 'args=', arguments);
                self.eltUserNotifications.html('<div class="alert alert-danger alert-block fade in" role="alert" data-aria-autofocus="true">' + msg + '</div>');
            });
    },
    /**
     * Runs when the IA JS is loaded.
     *
     * @returns {null} Nothing.
     */
    getProctorJsDone: function () {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.getProctorJsDone';
        window.console.log(fxn + '::Started');
        var self = M.block_integrityadvocate;

        $(document).bind('IA_Ready', function (script) {
            self.handleEventIAReady(script);
        });

        self.setupQuiz();
    },
    /**
     * Runs when the IA's JS-created IA_Ready event fires.
     *
     * @returns {null} Nothing.
     */
    handleEventIAReady: function (script) {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.handleEventIAReady';
        debug && window.console.log(fxn + 'Started with script=', script);
        var self = M.block_integrityadvocate;

        if (typeof window.IntegrityAdvocate != 'object') {
            console.error('FAILED to load window.IntegrityAdvocate object');
            // Silently exit.
            return;
        }

        window.console.log(fxn + '::Got window.IntegrityAdvocate=', window.IntegrityAdvocate);
        if (typeof window.IntegrityAdvocate.status === 'string' && window.IntegrityAdvocate.status === 'Not Required') {
            var identifiers = {
                appid: self.appid,
                courseid: self.courseid,
                moduleid: self.activityid,
                userid: self.participantidentifier
            };
            console.warn('IA is not enabled on the IA side for this activity identifiers shown below.  The admin/teacher should go into module overview > Activities tab to re-set "Enable Integrity Advocate" and re-set some Rules.', identifiers);
        }

        // Remember that we have started a session so we only close it once.
        self.sessionOpen();

        // Hide the loading gif and show the main content.
        self.proctorUILoaded();

        window.console.log(fxn + '::IA_Ready::Done');
    },
    /**
     * Setup JS needed for quiz IA functionality.
     *
     * @returns {null} Nothing.
     */
    setupQuiz: function () {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.setupQuiz';
        debug && window.console.log(fxn + '::Started');
        var self = M.block_integrityadvocate;

        // For quizzes, close the IA session using window.IntegrityAdvocate.endSession().
        // For non-quizzes, close the IA session using self.sessionClose() and/or db/events.php.  This is setup in loadProctorUi().
        if (self.isQuizAttempt) {
            window.console.log(fxn + '::This is a quiz attempt');
            if (self.proctorquizreviewpages) {
                debug && window.console.log(fxn + '::proctorquizreviewpages=true');
            } else {
                debug && window.console.log(fxn + '::proctorquizreviewpages=false so attach endSession to Next/Finish attempt button');

                // Quiz navigation sidebar "Finish attempt button".
                $('a.endtestlink').on('click.block_integrityadvocate', function () {
                    var fxn = 'M.block_integrityadvocate.setupQuiz.a.endtestlink.click';
                    window.console.log(fxn + '::Started');
                    e.preventDefault();
                    const promise = self.iaEndSession(e).then(e => { e.target.click(); });
                    debug && window.console.log(fxn + '::Done call to iaEndSession, result=', promise);
                });

                // Quiz body "Next"/"Finish attempt" button, but only if this is the last page of the quiz.
                var eltNextPageArr = self.eltDivMain.find('#responseform input[name="nextpage"]');
                if (eltNextPageArr.length > 0 && eltNextPageArr[0].value == -1) {
                    self.eltQuizNextButton.on('click.block_integrityadvocate', function (e) {
                        var fxn = 'M.block_integrityadvocate.setupQuiz.eltNextPageArr.click';
                        window.console.log(fxn + '::Started');
                        e.preventDefault();
                        const promise = self.iaEndSession(e).then(e => { e.target.click(); });
                        debug && window.console.log(fxn + '::Done call to iaEndSession, result=', promise);

                    });
                }
            }
        } else if (document.body.id === 'page-mod-quiz-review') {
            window.console.log(fxn + '::This is a quiz review page');

            if (self.proctorquizreviewpages) {
                debug && window.console.log(fxn + '::proctorquizreviewpages=false so attach endSession to Finish review button');

                // Quiz body "Finish review" button - one in the body, one in the sidebar block Quiz Navigation.
                self.eltQuizNextButtonSet.one('click.block_integrityadvocate', function (e,) {
                    var fxn = 'M.block_integrityadvocate.setupQuiz.eltQuizNextButtonSet.click';
                    window.console.log(fxn + '::Started');
                    e.preventDefault();
                    const promise = self.iaEndSession(e).then(e => { e.target.click(); });
                    debug && window.console.log(fxn + '::Done call to iaEndSession, result=', promise);
                });
            }
        }
    },
    /**
     * Async wrapper around the IA JS call to end the IA session.
     * Sets up class variables and kick off this block JS functionality.
     *
     * @param {event} The event that triggered this action e.g. a click event.
     * @returns {event} The event you passed in, so you can trigger it in a .then().
     */
    iaEndSession: async function (e) {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.iaEndSession';
        debug && window.console.log(fxn + '::Started');
        await window.IntegrityAdvocate.endSession(() => {
            window.console.log(fxn + '::Done IntegrityAdvocate.endSession()');
        });
        debug && window.console.log(fxn + '::Done');
        return e;
    },
    /**
     * Init function for this block called from PHP.
     * Sets up class variables and kick off this block JS functionality.
     *
     * @param {class} Y Moodle Yahoo.
     * @param {string} proctorjsurl URL to the IA proctor JS.
     * @param {bool} proctorquizreviewpages True to show proctoring on quiz summary and review pages.
     * @returns {null} Nothing.
     */
    blockinit: function (Y, proctorjsurl, proctorquizreviewpages) {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.blockinit';
        debug && window.console.log(fxn + '::Started with proctorjsurl=', proctorjsurl);
        debug && window.console.log(fxn + '::Started with proctorquizreviewpages=', proctorquizreviewpages);
        var self = M.block_integrityadvocate;

        // Register input vars for re-use.
        var url = new URL(proctorjsurl);
        var params = new URLSearchParams(url.search.replace(/\&amp;/g, '&'));
        debug && window.console.log(fxn + '::Parsed proctorjsurl=', url);
        params.forEach(function (value, key) {
            self[decodeURIComponent(key)] = decodeURIComponent(value);
        });
        self.proctorquizreviewpages = proctorquizreviewpages === '1';

        // Register derived vars for re-use.
        self.isQuizAttempt = (document.body.id === 'page-mod-quiz-attempt');
        self.isScormPlayerSameWindow = (document.body.id === 'page-mod-scorm-player') && !M.mod_scormform;
        self.isScormEntryNewWindow = (document.body.id === 'page-mod-scorm-view') && typeof M.mod_scormform !== 'undefined';
        if (self.isScormEntryNewWindow || self.isScormPlayerSameWindow) {
            self.eltScormEnter = $('#scormviewform input[type="submit"]');
        }
        self.eltUserNotifications = $('#user-notifications');
        self.eltDivMain = $('div[role="main"]');
        self.eltQuizNextButton = $('#mod_quiz-next-nav');
        self.eltQuizNextButtonSet = $('.mod_quiz-next-nav');

        debug && window.console.log(fxn + '::After gathering vars, this block self=', self);

        // Handlers for different kinds of pages - this is for any required setup before the IA JS is loaded.
        switch (true) {
            case (self.isQuizAttempt):
                debug && window.console.log(fxn + '::This is a quiz attempt page');
                // Disables the Next button until IA JS is loaded.
                self.eltQuizNextButtonSet.attr('disabled', 1).on('click.block_integrityadvocate.disable', false);
                self.loadProctorUi(proctorjsurl);
                break;
            case (self.isScormEntryNewWindow):
                debug && window.console.log(fxn + '::This is a SCORM new "popup" entry page');
                // Trigger the IA proctoring only on button click.
                self.eltScormEnter.on('click.block_integrityadvocate', function (e) {
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
                debug && window.console.log(fxn + '::This is the default page handler');
                self.loadProctorUi(proctorjsurl);
        }

    },
    /**
     * Test if str is an http(s) URL.
     * Adapted from https://thispointer.com/javascript-check-if-string-is-url/ .
     * This is not meant to be the perfect regex, just a quick sanity check.
     *
     * @param {string} str
     * @returns {bool} True if str is an http(s) URL.
     */
    isHttpUrl: function (str) {
        return /^(?:\w+:)?\/\/([^\s\.]+\.\S{2}|localhost[\:?\d]*)\S*$/.test(str);
    }
};


