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
     * Avoid closing the IA session multiple times.
     */
    hasClosedIASession: false,

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
        window.console.log(fxn + '::Started');
        var self = M.block_integrityadvocate;

        try {
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
                        window.console.warning(fxn + '::ajax.fail', errorThrown);
                        window.console.log('textStatus', textStatus);
                        alert(M.util.get_string('unknownerror', 'moodle') + ' ' + fxn + '::ajax.fail');
                    }
                }]);
            });
        } catch (error) {
            window.console.warning(fxn + '::Caught an error on the ajax call', error);
        }

        window.console.log(fxn + '::Done');
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

        try {
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
                        window.console.warning(fxn + '::ajax.fail', errorThrown);
                        window.console.log('textStatus', textStatus);
                        window.IntegrityAdvocate.endSession();
                        alert(M.util.get_string('unknownerror', 'moodle') + ' M.block_integrityadvocate.sessionClose::ajax.fail');
                    }
                }]);
            });
        } catch (error) {
            window.console.warning(fxn + '::Caught an error on the ajax call', error);
        }

        debug && window.console.log(fxn + '::Done');
    },
    /**
     * Signal we are starting the IA proctoring session.
     *
     * @param {function} callback Function to call when done.
     * @returns {null} Nothing.
     */
    startProctoring: function (callback) {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.startProctoring';
        debug && window.console.log(fxn + '::Started');
        var self = M.block_integrityadvocate;

        var attemptid = new URLSearchParams(new URL(window.location.href).search).get('attempt');
        if (attemptid == null || !Number.isInteger(Number(attemptid))) {
            // We are not on a quiz attempt page so do nothing.
            return;
        }

        try {
            require(['core/ajax'], function (ajax) {
                ajax.call([{
                    methodname: 'block_integrityadvocate_start_proctoring',
                    args: {
                        attemptid: attemptid, // TODO put in the quiz attempt id.
                    },
                    done: function (data) {
                        window.console.log(fxn + '::ajax.done::Done the call to block_integrityadvocate_start_proctoring; data=', data);
                        // While you can override the quiz timer UI in JS, the time you actually submit the quiz is still checked vs the server-side value.
                        if (M.mod_quiz.timer.endtime > 0 && (typeof data.result === 'number') && Number.isInteger(data.result) && data.result > 0) {
                            // The PHP returns epoch time in seconds, but JS uses milliseconds.
                            newtimeleft = (M.mod_quiz.timer.endtime - Date.now()) / 1000 + data.result;
                            debug && window.console.log(fxn + '::Timer: Original endtime=' + M.mod_quiz.timer.endtime + '; newtimeleft=' + newtimeleft);
                            if (newtimeleft > 0) {
                                M.mod_quiz.timer.updateEndTime(newtimeleft);
                            } else {
                                debug && window.console.error(fxn + '::Skip bc timeleft <= 0');
                            }
                        } else {
                            // This probably does not warrant a warning/error.
                            window.console.log(fxn + '::Timer: Timer unused or data.result is zero/invalid');
                        }
                        typeof callback === 'function' && callback(data);
                    },
                    fail: function (xhr_unused, textStatus, errorThrown) {
                        window.console.warning(fxn + '::ajax.fail', errorThrown);
                        window.console.log('textStatus', textStatus);
                        window.IntegrityAdvocate.endSession();
                        alert(M.util.get_string('unknownerror', 'moodle') + ' M.block_integrityadvocate.startProctoring::ajax.fail');
                    }
                }]);
            });
        } catch (error) {
            window.console.warning(fxn + '::Caught an error on the ajax call', error);
        }

        debug && window.console.log(fxn + '::Done');
    },
    /**
     * Hide the loading gif and show the main content.
     *
     * @returns {null} Nothing.
     */
    showActivityContent: function () {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.showActivityContent';
        window.console.log(fxn + '::Started');
        var self = M.block_integrityadvocate;

        self.eltUserNotifications.css({ 'background-image': 'none' }).height('auto');
        var eltMainContent = $('#responseform, #scormpage, div[role="main"]');
        switch (true) {
            case self.isQuizAttempt:
                window.console.log(fxn + '.isQuizAttempt::IA is ready: Enable the submit button', $('.mod_quiz-next-nav'));
                // Commented out bc prevents Moodle quiz binding the button properly.
                // Disabled: $('.mod_quiz-next-nav').removeAttr('disabled').off('click.block_integrityadvocate.disable');.
                $('#block_integrityadvocate_hidequiz').remove();
                window.console.log(fxn + '.isQuizAttempt::IA is ready: Show the quiz questions', eltMainContent);
                eltMainContent.show(0, function () { window.console.log(fxn + 'isQuizAttempt: Done: Enable the submit button and show the main content'); });

                self.startProctoring();
                break;
            case self.isScormPlayerSameWindow:
                debug && window.console.log(fxn + '::On SCORM samewindow, show the content and monitor for page close');
                eltMainContent.show();

                var elt = $('a.btn-secondary[title="' + M.util.get_string('exitactivity', 'scorm') + '"]');
                elt.on('click.block_integrityadvocate', function (e) {
                    debug && window.console.log('M.block_integrityadvocate.exitactivity.on(click)::started');
                    self.sessionClose(function () {
                        debug && window.console.log('M.block_integrityadvocate.sessionClose.promise.done::started');
                        elt.off('click.block_integrityadvocate');
                        elt[0].click();
                    });
                    e.preventDefault();
                    return false;
                });
                break;
            case self.isScormEntryNewWindow:
                debug && window.console.log(fxn + '::On SCORM newwindow, show the Enter form and monitor for page close');
                self.showMainContent();
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
    loadProctorJs: function (proctorjsurl) {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.loadProctorJs';
        debug && window.console.log(fxn + '::Started with proctorjsurl=', proctorjsurl);
        var self = M.block_integrityadvocate;

        if (!self.isHttpUrl(proctorjsurl)) {
            window.console.error(fxn + '::Invalid input param proctorjsurl=', proctorjsurl);
            return;
        }

        var decodedUrl = self.decodeEntities(proctorjsurl);
        debug && window.console.log(fxn + '::About to getScript() with decodedUrl=', decodedUrl);
        // With $.getScript(....success(response), the response is undefined if the request is from another domain.
        // Using $.ajax fails bc it blocks CORS.
        $.getScript(decodedUrl)
            .done(function () {
                debug && window.console.log(fxn + '.getScript().done');
                self.onProctorJsLoaded();
            })
            .fail(function (jqxhr, settings, exception) {
                // Hide the loading gif.
                $('#block_integrityadvocate_loading').remove();
                self.eltUserNotifications.css({ 'background-image': 'none' }).height('auto');
                self.showMainContent();
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
    onProctorJsLoaded: function () {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.onProctorJsLoaded';
        window.console.log(fxn + '::Started');
        var self = M.block_integrityadvocate;

        $(document).bind('IA_Ready', function (script) {
            self.onEventIAReady(script);
        });
    },
    /**
     * Runs when the IA's JS-created IA_Ready event fires.
     * This happens regardless of whether IA is enabled for this activity on the IA side.
     *
     * @returns {null} Nothing.
     */
    onEventIAReady: function (script) {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.onEventIAReady';
        debug && window.console.log(fxn + 'Started with script=', script);
        var self = M.block_integrityadvocate;

        if (typeof window.IntegrityAdvocate != 'object') {
            window.console.error('FAILED to load window.IntegrityAdvocate object');
            // Silently exit.
            return;
        }

        try {
            self.showActivityContent();
            window.console.log(fxn + '::Done showActivityContent()');
        } catch (error) {
            window.console.log(fxn + '::Caught an error running showActivityContent()');
        }

        window.console.log(fxn + '::Got window.IntegrityAdvocate=', window.IntegrityAdvocate);
        if (typeof window.IntegrityAdvocate.endSession == 'undefined') {
            var identifiers = {
                appid: self.appid,
                courseid: self.courseid,
                moduleid: self.activityid,
                userid: self.participantidentifier
            };
            window.console.log('IntegrityAdvocate is not enabled on the IA side for this activity identifiers shown below.  The admin/teacher should go into module overview > Activities tab to re-set "Enable Integrity Advocate" and re-set some Rules.', identifiers);
            return;
        }

        // Remember that we have started a session so we only close it once.
        self.sessionOpen();
        window.console.log(fxn + '::Done sessionOpen()');

        self.onEventIaReadySetupQuiz();
        window.console.log(fxn + '::Done onEventIaReadySetupQuiz()');

        window.console.log(fxn + '::Done');
    },
    /**
     * Setup JS needed for quiz IA functionality.
     *
     * @returns {null} Nothing.
     */
    onEventIaReadySetupQuiz: function () {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.onEventIaReadySetupQuiz';
        debug && window.console.log(fxn + '::Started with document.body.id=', document.body.id);
        var self = M.block_integrityadvocate;

        // For quizzes, close the IA session using window.IntegrityAdvocate.endSession().
        // For non-quizzes, close the IA session using self.sessionClose() and/or db/events.php.  This is setup in loadProctorJs().
        if (self.isQuizAttempt) {
            window.console.log(fxn + '::This is a quiz attempt');
            if (self.proctorquizreviewpages) {
                debug && window.console.log(fxn + '::proctorquizreviewpages=' + self.proctorquizreviewpages + ' so do nothing');
            } else {
                debug && window.console.log(fxn + '::proctorquizreviewpages=false so attach endSession to a few places');

                // Close IA session on: Quiz navigation sidebar "Finish attempt button".
                $('a.endtestlink').one('click.block_integrityadvocate', function (e) {
                    var fxn = 'M.block_integrityadvocate.onEventIaReadySetupQuiz.a.endtestlink.click';
                    window.console.log(fxn + '::Started with e=', e);
                    e.preventDefault();
                    const promise = self.endIaSession(e)
                        .then(e => {
                            self.hasClosedIASession = true;
                            debug && window.console.log(fxn + '::Done call to endIaSession, result=', promise);
                            e.target.click();
                        })
                        .catch(error => {
                            window.console.log(fxn + '::endIaSession promise::Error on endIaSession(); error=', error);
                        });
                });

                // Close IA session on: Quiz body "Next"/"Finish attempt" button, but only if this is the last page of the quiz.
                var eltNextPageArr = self.eltDivMain.find('#responseform input[name="nextpage"]');
                if (eltNextPageArr.length > 0 && eltNextPageArr[0].value == -1) {
                    // Different versions of Moodle use different selectors.
                    $('#mod_quiz-next-nav, .mod_quiz-next-nav').one('click.block_integrityadvocate', function (e) {
                        var fxn = 'M.block_integrityadvocate.onEventIaReadySetupQuiz.eltNextPageArr.click';
                        window.console.log(fxn + '::Started with e=', e);
                        e.preventDefault();
                        const promise = self.endIaSession(e)
                            .then(e => {
                                self.hasClosedIASession = true;
                                debug && window.console.log(fxn + '::Done call to endIaSession, result=', promise);
                                e.target.click();
                            })
                            .catch(error => {
                                window.console.log(fxn + '::endIaSession promise::Error on endIaSession(); error=', error);
                            });
                    });
                }

                // Close IA session on: The quiz timer submits the form.
                // Override the Moodle core mod_quiz bc other options did not work:.
                // Z - Intercepting form.submit().
                // Z - Intercepting (input[name=finishattempt]).
                M.mod_quiz.timer.originalUpdate = M.mod_quiz.timer.update;
                M.mod_quiz.timer.update = function () {
                    var fxn = 'M.block_integrityadvocate.M.mod_quiz.timer.update';
                    debug && window.console.log(fxn + '::Started');

                    // This next line copied from mod/quiz/module.js::M.mod_quiz.timer.update() MOODLE_404_STABLE 2024Sep.
                    var secondsleft = Math.floor((M.mod_quiz.timer.endtime - new Date().getTime()) / 1000);
                    // If time has expired, set the hidden form field that says time has expired and submit
                    if (secondsleft < 0 && !self.hasClosedIASession) {
                        debug && window.console.log(fxn + '::Quiz timer expired and we should close the IA session');
                        const promise = self.endIaSession(e)
                            .then(e => {
                                window.console.log(fxn + '::endIaSession promise::Done endIaSession()');
                                self.hasClosedIASession = true;
                                // The original M.mod_quiz.timer.update() function will do the form submit.
                            })
                            .catch(error => {
                                window.console.log(fxn + '::endIaSession promise::Error on endIaSession(); error=', error);
                            });
                        //Disabled bc not needed: window.console.log(fxn + '::After call to endIaSession, result=', promise); .
                    }

                    // Call the original submission process.
                    M.mod_quiz.timer.originalUpdate();
                };
            }
        } else if (document.body.id === 'page-mod-quiz-review') {
            window.console.log(fxn + '::This is a quiz review page');

            if (self.proctorquizreviewpages) {
                debug && window.console.log(fxn + '::Attach endSession to Finish review button');

                // Quiz body "Finish review" button - one in the body, one in the sidebar block Quiz Navigation.
                $('.mod_quiz-next-nav').one('click.block_integrityadvocate', function (e) {
                    var fxn = 'M.block_integrityadvocate.onEventIaReadySetupQuiz.eltQuizNextButtonSet.click';
                    window.console.log(fxn + '::Started with e=', e);
                    e.preventDefault();
                    const promise = self.endIaSession(e)
                        .then(e => {
                            self.hasClosedIASession = true;
                            debug && window.console.log(fxn + '::Done call to endIaSession, result=', promise);
                            e.target.click();
                        })
                        .catch(error => {
                            window.console.log(fxn + '::endIaSession promise::Error on endIaSession(); error=', error);
                        });
                });
            }
        } else if (document.body.id === 'page-mod-quiz-summary') {
            window.console.log(fxn + '::This is a quiz summary page AND self.quizshowsreviewpage=', self.quizshowsreviewpage);

            if (self.proctorquizreviewpages && !self.quizshowsreviewpage) {
                // We get here if the quiz is configured to not show a review page.
                debug && window.console.log(fxn + '::Quiz review page will not show so attach endSession to Submit all and Finish button');

                // Moodle ~3.5 "Submit all and finish button" throws up a confirmation modal with another "Submit all and finish button".
                var selectorModal = '.modal-dialog-scrollable .btn-primary, .moodle-dialogue-confirm .btn-primary'
                self.waitForElt(selectorModal)
                    .then(() => {
                        window.console.log(fxn + '::Found selectorModal=', selectorModal);
                        $(selectorModal).one('click.block_integrityadvocate', function (e) {
                            var fxn = 'M.block_integrityadvocate.onEventIaReadySetupQuiz.modalSubmitAllAndFinish.click';
                            window.console.log(fxn + '::Started with e=', e);
                            e.preventDefault();
                            const promise = self.endIaSession(e)
                                .then(e => {
                                    self.hasClosedIASession = true;
                                    debug && window.console.log(fxn + '::Done call to endIaSession, result=', promise);
                                    e.target.click();
                                })
                                .catch(error => {
                                    window.console.log(fxn + '::endIaSession promise::Error on endIaSession(); error=', error);
                                });
                        });
                    })
                    .catch(error => {
                        window.console.log(fxn + '::promise::Error on waitForElt(); error=', error);
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
    endIaSession: async function (e) {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.endIaSession';
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
     * @param {bool} quizshowsreviewpage True if the quiz shows the review page after the summary page.
     * @returns {null} Nothing.
     */
    blockinit: function (Y, proctorjsurl, proctorquizinfopage, proctorquizreviewpages, quizshowsreviewpage) {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.blockinit';
        debug && window.console.log(fxn + '::Started with proctorquizinfopage=' + proctorquizinfopage + '; proctorquizreviewpages=' + proctorquizreviewpages + '; quizshowsreviewpage=' + quizshowsreviewpage + '; proctorjsurl=' + proctorjsurl);
        var self = M.block_integrityadvocate;

        // Register input vars for re-use.
        var url = new URL(proctorjsurl);
        var params = new URLSearchParams(url.search.replace(/\&amp;/g, '&'));
        debug && window.console.log(fxn + '::Parsed proctorjsurl=', url);
        params.forEach(function (value, key) {
            self[decodeURIComponent(key)] = decodeURIComponent(value);
        });
        self.proctorjsurl = proctorjsurl;
        self.proctorquizinfopage = parseInt(proctorquizinfopage) === 1;
        self.proctorquizreviewpages = parseInt(proctorquizreviewpages) === 1;
        self.quizshowsreviewpage = parseInt(quizshowsreviewpage) === 1;

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

        debug && window.console.log(fxn + '::After gathering vars, this block self=', self);

        // Handlers for different kinds of pages - this is for any required setup before the IA JS is loaded.
        switch (true) {
            case (document.body.id.startsWith('page-mod-quiz-')):
                self.onBlockInitSetupQuiz();
                break;
            case (document.body.id.startsWith('page-mod-scorm--')):
                self.onBlockInitSetupScorm();
                break;
            default:
                debug && window.console.log(fxn + '::This is the default page handler');
                self.loadProctorJs(self.proctorjsurl);
                break;
        }
    },
    onBlockInitSetupQuiz: function () {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.onBlockInitSetupQuiz';
        debug && window.console.log(fxn + '::Started');
        var self = M.block_integrityadvocate;

        if (document.body.id === 'page-mod-quiz-attempt') {
            debug && window.console.log(fxn + '::This is a quiz attempt page');
            // Disables the Next button until IA JS is loaded.
            // Commented out bc prevents Moodle quiz binding the button properly.
            // Disabled: $('.mod_quiz-next-nav').attr('disabled', 1).on('click.block_integrityadvocate.disable', false);
            self.loadProctorJs(self.proctorjsurl);
        } else if (document.body.id === 'page-mod-quiz-view') {
            debug && window.console.log(fxn + '::This is a quiz view page with self.proctorquizreviewpages=' + self.proctorquizreviewpages);
            if (self.proctorquizinfopage) {
                self.loadProctorJs(self.proctorjsurl);
            } else {
                self.showMainContent();
            }
        } else if (['page-mod-quiz-summary', 'page-mod-quiz-review'].includes(document.body.id)) {
            debug && window.console.log(fxn + '::This is a quiz summary or review page with self.proctorquizreviewpages=' + self.proctorquizreviewpages);
            if (self.proctorquizreviewpages) {
                self.loadProctorJs(self.proctorjsurl);
            } else {
                self.showMainContent();
            }
        } else {
            debug && window.console.log(fxn + '::This is a quiz page of unknown type');
            self.showMainContent();
        }
    },
    onBlockInitSetupScorm: function () {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.onBlockInitSetupScorm';
        debug && window.console.log(fxn + '::Started');
        var self = M.block_integrityadvocate;

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
            self.loadProctorJs(self.proctorjsurl);
            return false;
        });
    },
    showMainContent: function () {
        $('div[role="main"]').show();
        $('#block_integrityadvocate_hidequiz').remove();
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
    },
    /**
     * Wait for an element to exist, then return the resolved promise result.
     * Source https://stackoverflow.com/a/61511955 .
     *
     * @param {string} An element selector string.
     * @returns {*} A resovled promise.
     */
    waitForElt: function (selector) {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.waitForElt';
        debug && window.console.log(fxn + '::Started with selector=', selector);
        return new Promise(resolve => {
            if (document.querySelector(selector)) {
                debug && window.console.log(fxn + '::Found the selector=', selector);
                return resolve(document.querySelector(selector));
            }

            const observer = new MutationObserver(mutations => {
                if (document.querySelector(selector)) {
                    observer.disconnect();
                    resolve(document.querySelector(selector));
                }
            });

            // If you get "parameter 1 is not of type 'Node'" error, see https://stackoverflow.com/a/77855838/492336
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        });
    }
};



