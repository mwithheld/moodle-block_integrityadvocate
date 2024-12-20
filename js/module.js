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
     * Decode HTMLEntities from string.
     *
     * @ref https://stackoverflow.com/a/31350391
     * @param {type} encodedString
     * @returns {.document@call;createElement.value|textArea.value}
     */
    decodeEntities: (encodedString) => {
        if (!encodedString) return '';
        const textArea = document.createElement('textarea');
        textArea.innerHTML = encodedString;
        return textArea.value;
    },
    /**
     * Open an IA proctoring session.
     *
     * @returns {null} Nothing.
     */
    sessionOpen: () => {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.sessionOpen';
        window.console.log(fxn + '::Started');
        const self = M.block_integrityadvocate;

        try {
            require(['core/ajax'], (ajax) => {
                ajax.call([{
                    methodname: 'block_integrityadvocate_session_open',
                    args: {
                        appid: self.appid,
                        courseid: self.courseid,
                        moduleid: self.activityid,
                        userid: self.participantidentifier
                    },
                    done: () => {
                        debug && window.console.log(fxn + '::ajax.done');
                    },
                    fail: (xhrUnused, textStatus, errorThrown) => {
                        window.console.warn(fxn + '::ajax.fail', errorThrown);
                        window.console.log('textStatus', textStatus);
                        require(['core/notification'], (notification) => {
                            notification.alert(M.util.get_string('unknownerror', 'moodle'), fxn + '::ajax.fail', 'Close');
                        });
                    }
                }]);
            });
        } catch (error) {
            window.console.error(fxn + '::Caught an error on the ajax call; error=', error);
        }

        window.console.log(fxn + '::Done');
    },
    /**
     * Close an IA proctoring session.
     *
     * @param {function} callback Function to call when done.
     * @returns {null} Nothing.
     */
    sessionClose: (callback) => {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.sessionClose';
        debug && window.console.log(fxn + '::Started');
        const self = M.block_integrityadvocate;

        try {
            require(['core/ajax'], (ajax) => {
                ajax.call([{
                    methodname: 'block_integrityadvocate_session_close',
                    args: {
                        appid: self.appid,
                        courseid: self.courseid,
                        moduleid: self.activityid,
                        userid: self.participantidentifier
                    },
                    done: () => {
                        debug && window.console.log(fxn + '::ajax.done');
                        typeof callback === 'function' && callback();
                    },
                    fail: (xhrUnused, textStatus, errorThrown) => {
                        window.console.warn(fxn + '::ajax.fail', errorThrown);
                        window.console.log('textStatus', textStatus);
                        window.IntegrityAdvocate.endSession();
                    }
                }]);
            });
        } catch (error) {
            window.console.error(fxn + '::Caught an error on the ajax call; error=', error);
        }

        debug && window.console.log(fxn + '::Done');
    },
    /**
     * Signal we are starting the IA proctoring session.
     *
     * @param {function} callback Function to call when done.
     * @returns {null} Nothing.
     */
    startProctoring: (callback) => {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.startProctoring';
        debug && window.console.log(fxn + '::Started');
        const self = M.block_integrityadvocate;

        var attemptid = new URLSearchParams(new URL(window.location.href).search).get('attempt');
        if (!attemptid || !Number.isInteger(Number(attemptid))) {
            // We are not on a quiz attempt page so do nothing.
            return;
        }

        try {
            require(['core/ajax'], (ajax) => {
                ajax.call([{
                    methodname: 'block_integrityadvocate_start_proctoring',
                    args: {
                        attemptid: attemptid,
                    },
                    done: (data) => {
                        window.console.log(fxn + '::ajax.done::Done the call to block_integrityadvocate_start_proctoring; data=', data);
                        // While you can override the quiz timer UI in JS, the time you actually submit the quiz is still checked vs the server-side value.
                        if (M.mod_quiz.timer.endtime > 0 && (typeof data.result === 'number') && Number.isInteger(data.result) && data.result > 0) {
                            // The PHP returns epoch time in seconds, but JS uses milliseconds.
                            var newtimeleft = (M.mod_quiz.timer.endtime - Date.now()) / 1000 + data.result;
                            debug && window.console.log(fxn + '::Timer: Original endtime=' + M.mod_quiz.timer.endtime + '; newtimeleft=' + newtimeleft);
                            if (newtimeleft > 0) {
                                M.mod_quiz.timer.updateEndTime(newtimeleft);
                            } else {
                                debug && window.console.log(fxn + '::Skip bc timeleft <= 0');
                            }
                        } else {
                            // This probably does not warrant a warning/error.
                            window.console.log(fxn + '::Timer: Timer unused or data.result is zero/invalid');
                        }
                        typeof callback === 'function' && callback(data);
                    },
                    fail: (xhrUnused, textStatus, errorThrown) => {
                        window.console.warn(fxn + '::ajax.fail:: Got errorThrown=', errorThrown);
                        window.console.log(fxn + '::ajax.fail:: Got textStatus=', textStatus);
                        window.IntegrityAdvocate.endSession();
                        require(['core/notification'], (notification) => {
                            notification.alert(M.util.get_string('unknownerror', 'moodle'), 'M.block_integrityadvocate.startProctoring::ajax.fail; errorThrown=' + errorThrown, 'Close');
                        });
                    }
                }]);
            });
        } catch (error) {
            window.console.error(fxn + '::Caught an error on the ajax call; error=', error);
        }

        debug && window.console.log(fxn + '::Done');
    },
    /**
     * Hide the loading gif and show the main content.
     *
     * @returns {null} Nothing.
     */
    showActivityContent: () => {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.showActivityContent';
        window.console.log(fxn + '::Started');
        const self = M.block_integrityadvocate;

        self.hideLoadingGif();
        const eltMainContent = $('#responseform, #scormpage, div[role="main"]');

        switch (true) {
            case self.isQuizAttempt:
                window.console.log(fxn + '.isQuizAttempt::IA is ready: Enable the submit button', $('.mod_quiz-next-nav'));
                // Commented out bc prevents Moodle quiz binding the button properly.
                // Disabled: $('.mod_quiz-next-nav').removeAttr('disabled').off('click.block_integrityadvocate.disable');.
                document.querySelector('#block_integrityadvocate_hidequiz')?.remove();
                window.console.log(fxn + '.isQuizAttempt::IA is ready: Show the quiz questions', eltMainContent);
                eltMainContent.show(0, () => { window.console.log(fxn + 'isQuizAttempt: Done: Enable the submit button and show the main content'); });

                self.startProctoring();
                break;
            case self.isScormPlayerSameWindow:
                debug && window.console.log(fxn + '::On SCORM samewindow, show the content and monitor for page close');
                eltMainContent.show();

                var elt = $('a.btn-secondary[title="' + M.util.get_string('exitactivity', 'scorm') + '"]');
                elt.on('click.block_integrityadvocate', (e) => {
                    debug && window.console.log('M.block_integrityadvocate.exitactivity.on(click)::started');
                    self.sessionClose(() => {
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
                document.querySelector('#block_integrityadvocate_loading')?.remove();
                window.addEventListener('beforeunload', () => {
                    debug && window.console.log(fxn + '::Exiting the window - close the IA session');
                    self.sessionClose();
                });
                self.eltScormEnter?.removeAttr('disabled').off('click.block_integrityadvocate').click().attr('disabled', 'disabled');
                break;
            default:
                debug && window.console.log(fxn + '::This is the default page handler');
                eltMainContent.show();
        }
    },
    /**
     * Hide the loading gif.
     *
     * @returns {null} Nothing.
     */
    hideLoadingGif: () => {
        const self = M.block_integrityadvocate;
        document.getElementById('block_integrityadvocate_loading')?.remove();
        self.eltUserNotifications.css({ 'background-image': 'none' }).height('auto');
    },
    /**
     * AJAX-load the proctor UI JS and run anything needed after.
     * The proctorjsurl is per-user and time-encoded unique, so there is no point in tryng to cache it.
     *
     * @param {string} proctorjsurl URL to the IA proctor JS.
     * @returns {null} Nothing.
     */
    loadProctorJs: (proctorjsurl) => {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.loadProctorJs';
        debug && window.console.log(fxn + '::Started with proctorjsurl=', proctorjsurl);
        const self = M.block_integrityadvocate;

        if (!self.isHttpUrl(proctorjsurl)) {
            window.console.error(fxn + '::Invalid input param proctorjsurl=', proctorjsurl);
            return;
        }

        var decodedUrl = self.decodeEntities(proctorjsurl);
        debug && window.console.log(fxn + '::About to getScript() with decodedUrl=', decodedUrl);
        // With $.getScript(....success(response), the response is undefined if the request is from another domain.
        // Using $.ajax fails bc it blocks CORS.
        const originalAlert = window.alert;
        try {
            // Temporarily override window.alert to suppress any alerts from the script.
            window.alert = (message) => {
                msg = 'The remote script threw an alert. This happens when the IA activity rules is set to [Level 1], or to [DEMO + no rules]. The remote alert message= ';
                window.console.warn(fxn + '::window.alert::Suppressed alert ' + msg, message);
                self.hideLoadingGif();
                require(['core/notification'], (notification) => {
                    notification.addNotification({
                        message: msg + ' ' + message,
                        type: "err"
                    });
                });

                debug && window.console.warn(fxn + '::window.alert::Add a Back to course button');
                var buttonId = 'block_integrityadvocate_backtocourse';
                var eltMain = document.querySelector('div[role="main"]');
                eltMain && (eltMain.innerHTML = '<button type="submit" class="btn btn-secondary" id="' + buttonId + '">' + M.util.get_string('closebuttontitle', 'core') + '</button>');
                document.querySelector('#' + buttonId).addEventListener('click', () => {
                    window.location.href = M.cfg.wwwroot + '/course/view.php?id=' + M.cfg.courseId;
                });
            };
            $.getScript(decodedUrl)
                .done(() => {
                    debug && window.console.log(fxn + '.getScript().done');
                    self.onProctorJsLoaded();
                })
                .fail((jqxhr, settings, exception) => {
                    window.console.log(fxn + '.getScript.fail::Started with exception=', exception);
                    self.hideLoadingGif();
                    self.showMainContent();
                    // Dump out some info about what went wrong.
                    var msg = M.util.get_string('proctorjs_load_failed', 'block_integrityadvocate');
                    if (exception.toString() !== 'error') {
                        msg += "Error details:\n" + exception.toString();
                    }

                    window.console.log(fxn + '.getScript.fail::', msg, 'args=', arguments);
                    self.eltUserNotifications.html('<div class="alert alert-danger alert-block fade in" role="alert" data-aria-autofocus="true">' + msg + '</div>');
                })
                .always(() => {
                    // Restore the original alert function after the request completes.
                    window.alert = originalAlert;
                });
        } catch (error) {
            window.console.console.error(fxn + '::Error loading remote script with decodedUrl='.decodedUrl, error);
            window.alert = originalAlert;
        }
    },
    /**
     * Runs when the IA JS is loaded.
     *
     * @returns {null} Nothing.
     */
    onProctorJsLoaded: () => {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.onProctorJsLoaded';
        window.console.log(fxn + '::Started');
        const self = M.block_integrityadvocate;

        document.addEventListener('IA_Ready', (script) => self.onEventIAReady(script));
    },
    /**
     * Runs when the IA's JS-created IA_Ready event fires.
     * This happens regardless of whether IA is enabled for this activity on the IA side.
     *
     * @returns {null} Nothing.
     */
    onEventIAReady: (script) => {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.onEventIAReady';
        debug && window.console.log(fxn + 'Started with script=', script);
        const self = M.block_integrityadvocate;

        if (typeof window.IntegrityAdvocate != 'object') {
            window.console.error('FAILED to load window.IntegrityAdvocate object');
            // Silently exit.
            return;
        }

        try {
            self.showActivityContent();
            window.console.log(fxn + '::Done showActivityContent()');
        } catch (error) {
            window.console.error(fxn + '::Caught an error running showActivityContent(); error=', error);
        }

        window.console.log(fxn + '::Got window.IntegrityAdvocate=', window.IntegrityAdvocate);
        if (!window.IntegrityAdvocate.endSession) {
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
    onEventIaReadySetupQuiz: () => {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.onEventIaReadySetupQuiz';
        debug && window.console.log(fxn + '::Started with document.body.id=', document.body.id);
        const self = M.block_integrityadvocate;

        const closeSession = (e = null) => {
            if (self.hasClosedIASession) {
                return;
            }
            const originalElement = e.target;
            // Remove the event listener on the element to avoid infinite loop.
            originalElement.removeEventListener('click', closeSession);

            return self.endIaSession(e)
                .then(e => {
                    self.hasClosedIASession = true;
                    window.console.log('Done call to endIaSession; About to click the original element', e);

                    // Trigger the original click event.
                    (e.type == 'click') && originalElement.click();
                })
                .catch(error => window.console.error(fxn + '::endIaSession promise::Error on endIaSession(); error=', error));
        };

        // For quizzes, close the IA session using window.IntegrityAdvocate.endSession().
        // For non-quizzes, close the IA session using self.sessionClose() and/or db/events.php.  This is setup in loadProctorJs().
        if (self.isQuizAttempt) {
            window.console.log(fxn + '::This is a quiz attempt; proctorquizreviewpages=', self.proctorquizreviewpages);
            if (self.proctorquizreviewpages) {
                window.console.log(fxn + '::proctorquizreviewpages=' + self.proctorquizreviewpages + ' so do nothing');
            } else {
                window.console.log(fxn + '::proctorquizreviewpages=false so attach endSession to a few places');

                // Close IA session on: Quiz navigation sidebar "Finish attempt button".
                const clickhandler = (e) => {
                    var fxn = 'M.block_integrityadvocate.onEventIaReadySetupQuiz.a.endtestlink.click';
                    window.console.log(fxn + '::Started with e=', e);
                    e.preventDefault();
                    closeSession(e);

                    // Remove the event listener to mimic jQuery.one().
                    link.removeEventListener('click', clickhandler);
                }
                document.querySelectorAll('a.endtestlink')?.forEach(link => {
                    link.addEventListener('click', e => clickhandler);
                });

                // Close IA session on: Quiz body "Next"/"Finish attempt" button, but only if this is the last page of the quiz.
                const eltNextPageArr = self.eltDivMain.find('#responseform input[name="nextpage"]');
                debug && window.console.log(fxn + '::Got eltNextPageArr=', eltNextPageArr);
                if (eltNextPageArr.length > 0 && eltNextPageArr[0].value == -1) {
                    // Different versions of Moodle use different selectors.
                    const clickhandler = (e) => {
                        var fxn = 'M.block_integrityadvocate.onEventIaReadySetupQuiz.eltNextPageArr.click';
                        window.console.log(fxn + '::Started with e=', e);
                        e.preventDefault();
                        closeSession(e);

                        // Remove the event listener to mimic jQuery.one().
                        e.currentTarget.removeEventListener('click', clickhandler);
                    }
                    document.querySelectorAll('#mod_quiz-next-nav, .mod_quiz-next-nav')?.forEach(element => {
                        element.addEventListener('click', clickhandler);
                    });
                    window.console.log(fxn + '::Attached endSession to Finish review button');
                }

                // Close IA session on: The quiz timer submits the form.
                self.onQuizTimerExpired(closeSession);
            }
        } else if (document.body.id === 'page-mod-quiz-review') {
            window.console.log(fxn + '::This is a quiz review page');

            if (self.proctorquizreviewpages) {
                debug && window.console.log(fxn + '::Got proctorquizreviewpages=' + self.proctorquizreviewpages + '; attach endSession to Finish review button');

                // Quiz body "Finish review" button - one in the body, one in the sidebar block Quiz Navigation.
                    const clickhandler = (e) => {
                        var fxn = 'M.block_integrityadvocate.onEventIaReadySetupQuiz.eltQuizNextButtonSet.click';
                        window.console.log(fxn + '::mod_quiz-next-nav::Started with e=', e);
                        e.preventDefault();
                        closeSession(e);

                        // Remove the event listener to mimic jQuery.one() behavior.
                        e.currentTarget.removeEventListener('click', clickhandler);
                    }
                    document.querySelectorAll('.mod_quiz-next-nav')?.forEach(element => {
                        element.addEventListener('click', e => clickhandler);
                    });
            }
        } else if (document.body.id === 'page-mod-quiz-summary') {
            window.console.log(fxn + '::This is a quiz summary page AND self.quizshowsreviewpage=', self.quizshowsreviewpage);

            if (self.proctorquizreviewpages && !self.quizshowsreviewpage) {
                // We get here if the quiz is configured to not show a review page.
                debug && window.console.log(fxn + '::Quiz review page will not show so attach endSession to Submit all and Finish button');

                // Moodle ~3.5 "Submit all and finish button" throws up a confirmation modal with another "Submit all and finish button".
                const selectorModal = '.modal-dialog-scrollable .btn-primary, .moodle-dialogue-confirm .btn-primary';
                self.waitForElt(selectorModal)
                    .then(() => {
                        window.console.log(fxn + '::Found selectorModal=', selectorModal);
                        const clickhandler = (e) => {
                            var fxn = 'M.block_integrityadvocate.onEventIaReadySetupQuiz.modalSubmitAllAndFinish.click';
                            window.console.log(fxn + '::waitForElt(selectModal)::Started with e=', e);
                            e.preventDefault();
                            closeSession(e);

                            // Remove the event listener to mimic jQuery.one() behavior.
                            this.removeEventListener('click', clickhandler);
                        }
                        document.querySelector(selectorModal)?.addEventListener('click', clickhandler);
                    })
                    .catch(error => {
                        window.console.error(fxn + '::promise::Error on waitForElt(); error=', error);
                    });
            }
        } else if (document.body.id === 'page-mod-quiz-view') {
            // We get to this page on the first click into the quiz before clicking to see the quiz questions.
            // And after the quiz attempt review after we click "Finish review".
            // There is not really a good way to tell if the user is about to do the quiz or is on their way out from completing the quiz.
            // So we don't closeSession() on the JS beforeunload event bc we assume the user will navigate intoto the quiz next.
            // This is only relevant if self.proctorquizinfopage is trueish.
        } else {
            window.console.log(fxn + '::This quiz page has no IA JS handling; document.body.id=[' + document.body.id + ']');
        }
    },
    /**
     * Close IA session on: The quiz timer submits the form.
     * Submits the quiz and navigates to the next page.
     *
     * Overrides the Moodle core mod_quiz bc other options did not work:.
     * Z - Intercepting form.submit().
     * Z - Intercepting (input[name=finishattempt]).
     *
     * @param {function} callback Function to run before passing control back to the original M.mod_quiz.timer.update().
     * @returns {null} Nothing.
     */
    onQuizTimerExpired: (callback) => {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.onQuizTimerExpired';
        debug && window.console.log(fxn + '::Started with callback=', callback);
        const self = M.block_integrityadvocate;

        M.mod_quiz.timer.originalUpdate = M.mod_quiz.timer.update;
        M.mod_quiz.timer.update = () => {
            var fxn = 'M.block_integrityadvocate.M.mod_quiz.timer.update';
            debug && window.console.log(fxn + '::Started');

            // This next line copied from mod/quiz/module.js::M.mod_quiz.timer.update() MOODLE_404_STABLE 2024Sep.
            const secondsleft = Math.floor((M.mod_quiz.timer.endtime - new Date().getTime()) / 1000);
            // If time has expired, set the hidden form field that says time has expired and submit
            if (secondsleft < 0 && !self.hasClosedIASession) {
                debug && window.console.log(fxn + '::Quiz timer expired and we should close the IA session');
                // We do not have an e parameter in this case.
                typeof callback === 'function' && callback();
                //Disabled bc not needed: window.console.log(fxn + '::After call to endIaSession, result=', promise); .
            }

            // Call the original submission process - this submits the form and navigates to the next page.
            M.mod_quiz.timer.originalUpdate();
        };
    },
    /**
     * Async wrapper around the IA JS call to end the IA session.
     * Sets up class variables and kick off this block JS functionality.
     *
     * @param {event} The event that triggered this action e.g. a click event.
     * @returns {event} The event you passed in, so you can trigger it in a .then().
     */
    endIaSession: async (e = null) => {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.endIaSession';
        window.console.log(fxn + '::Started with e, window.IntegrityAdvocate=', e, window.IntegrityAdvocate);

        try {
            if (typeof window.IntegrityAdvocate == 'object') {
                await window.IntegrityAdvocate.endSession(() => {
                    window.console.log(fxn + '::Done IntegrityAdvocate.endSession()');
                });
            }
        } catch (error) {
            window.console.error(fxn + '::Error during IntegrityAdvocate.endSession(); error=', error);
        }
        debug && window.console.log(fxn + '::Done');
        return e;
    },
    /**
     * Init for this block called from PHP.
     * Sets up class variables and kick off this block JS functionality.
     *
     * @param {class} Y Moodle Yahoo.
     * @param {string} versionstring Moodle and block version info.
     * @param {string} proctorjsurl URL to the IA proctor JS.
     * @param {string} proctorquizinfopage True to show proctoring on quiz view pages.
     * @param {bool} proctorquizreviewpages True to show proctoring on quiz summary and review pages.
     * @param {bool} quizshowsreviewpage True if the quiz shows the review page after the summary page.
     * @returns {null} Nothing.
     */
    blockinit: (Y, versionstring, proctorjsurl, proctorquizinfopage, proctorquizreviewpages, quizshowsreviewpage) => {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.blockinit';
        window.console.log(fxn + '::Started with versionstring=[' + versionstring + ']; proctorquizinfopage=' + proctorquizinfopage + '; proctorquizreviewpages=' + proctorquizreviewpages + '; quizshowsreviewpage=' + quizshowsreviewpage + '; proctorjsurl=' + proctorjsurl);
        const self = M.block_integrityadvocate;

        // Register input vars for re-use.
        var url = new URL(proctorjsurl);
        var params = new URLSearchParams(url.search.replace(/\&amp;/g, '&'));
        debug && window.console.log(fxn + '::Parsed proctorjsurl=', url);
        params.forEach((value, key) => {
            self[decodeURIComponent(key)] = decodeURIComponent(value);
        });

        self.versionstring = versionstring;
        self.proctorjsurl = proctorjsurl;
        self.proctorquizinfopage = parseInt(proctorquizinfopage) === 1;
        self.proctorquizreviewpages = parseInt(proctorquizreviewpages) === 1;
        self.quizshowsreviewpage = parseInt(quizshowsreviewpage) === 1;

        // Register derived vars for re-use.
        const bodyId = document.body.id;
        self.isQuizAttempt = (bodyId === 'page-mod-quiz-attempt');
        self.isScormPlayerSameWindow = (bodyId === 'page-mod-scorm-player') && !M.mod_scormform;
        self.isScormEntryNewWindow = (bodyId === 'page-mod-scorm-view') && typeof M.mod_scormform !== 'undefined';
        if (self.isScormEntryNewWindow || self.isScormPlayerSameWindow) {
            self.eltScormEnter = document.querySelector('#scormviewform input[type="submit"]');
        }

        // Cache common DOM elements.
        self.eltUserNotifications = $('#user-notifications');
        self.eltDivMain = $('div[role="main"]');
        self.eltQuizNextButton = $('#mod_quiz-next-nav');

        debug && window.console.log(fxn + '::After gathering vars, this block self=', self);

        // Handlers for different kinds of pages - this is for any required setup before the IA JS is loaded.
        switch (true) {
            case (bodyId.startsWith('page-mod-quiz-')):
                self.onBlockInitSetupQuiz();
                break;
            case (bodyId.startsWith('page-mod-scorm--')):
                self.onBlockInitSetupScorm();
                break;
            default:
                debug && window.console.log(fxn + '::This is the default page handler');
                self.loadProctorJs(self.proctorjsurl);
                break;
        }
    },
    /**
     * On block init setup quiz things.
     *
     * @returns {null} Nothing.
     */
    onBlockInitSetupQuiz: () => {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.onBlockInitSetupQuiz';
        debug && window.console.log(fxn + '::Started');
        const self = M.block_integrityadvocate;

        const bodyId = document.body.id;
        switch (bodyId) {
            case 'page-mod-quiz-attempt':
                debug && window.console.log(fxn + '::This is a quiz attempt page');
                // Disables the Next button until IA JS is loaded.
                // Commented out bc prevents Moodle quiz binding the button properly.
                // Disabled: $('.mod_quiz-next-nav').attr('disabled', 1).on('click.block_integrityadvocate.disable', false);
                self.loadProctorJs(self.proctorjsurl);
                break;
            case 'page-mod-quiz-view':
                debug && window.console.log(fxn + '::This is a quiz view page with self.proctorquizreviewpages=' + self.proctorquizreviewpages);
                if (self.proctorquizinfopage) {
                    self.loadProctorJs(self.proctorjsurl);
                } else {
                    self.showMainContent();
                }
                break;
            case 'page-mod-quiz-summary':
            // Fall through on purpose.
            case 'page-mod-quiz-review':
                debug && window.console.log(fxn + '::This is a quiz summary or review page with self.proctorquizreviewpages=' + self.proctorquizreviewpages);
                if (self.proctorquizreviewpages) {
                    self.loadProctorJs(self.proctorjsurl);
                } else {
                    self.showMainContent();
                }
                break;
            default:
                debug && window.console.log(fxn + '::This is a quiz page of unknown type; bodyId=[' + bodyId + ']');
                self.showMainContent();
        }
    },
    /**
     * On block init setup scorm things.
     *
     * @returns {null} Nothing.
     */
    onBlockInitSetupScorm: () => {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.onBlockInitSetupScorm';
        debug && window.console.log(fxn + '::Started');
        const self = M.block_integrityadvocate;

        // Trigger the IA proctoring only on button click.
        self.eltScormEnter && self.eltScormEnter.addEventListener('click', (e) => {
            var eltScormSubmit = document.querySelector('#scormviewform input[type="submit"]');
            eltScormSubmit && (eltScormSubmit.disabled = true);
            e.preventDefault();

            // Hide the SCORM content until the IA JS is loaded.
            self.eltDivMain.querySelectorAll('*').forEach(el => el.style.display = 'none');
            self.eltUserNotifications.style.textAlign = 'center';
            self.eltUserNotifications.innerHTML += '<i id="block_integrityadvocate_loading" class="fa fa-spinner fa-spin" style="font-size:72px"></i>';

            // Fix display of the loading gif.
            var offset = self.eltUserNotifications.getBoundingClientRect();
            window.scrollTo({
                top: window.scrollY + offset.top - 60,
                left: window.scrollX + offset.left - 20,
                behavior: 'smooth'
            });

            self.loadProctorJs(self.proctorjsurl);
            return false;
        });
    },
    /**
     * Show the main content (which is hidden by this block by default).
     *
     * @returns {null} Nothing.
     */
    showMainContent: () => {
        document.querySelector('div[role="main"]').style.display = 'block';
        document.querySelector('#block_integrityadvocate_hidequiz')?.remove();
    },
    /**
     * Test if str is an http(s) URL.
     * Adapted from https://thispointer.com/javascript-check-if-string-is-url/ .
     * This is not meant to be the perfect regex, just a quick sanity check.
     *
     * @param {string} str
     * @returns {bool} True if str is an http(s) URL.
     */
    isHttpUrl: (str) => /^(?:\w+:)?\/\/([^\s\.]+\.\S{2}|localhost[\:?\d]*)\S*$/.test(str),
    /**
     * Wait for an element to exist, then return the resolved promise result.
     * Source https://stackoverflow.com/a/61511955 .
     *
     * @param {string} An element selector string.
     * @returns {*} A resovled promise.
     */
    waitForElt: (selector) => {
        var debug = false;
        var fxn = 'M.block_integrityadvocate.waitForElt';
        debug && window.console.log(fxn + '::Started with selector=', selector);
        return new Promise(resolve => {
            if (document.querySelector(selector)) {
                debug && window.console.log(fxn + '::Found the selector=', selector);
                return resolve(document.querySelector(selector));
            }

            const observer = new MutationObserver(() => {
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