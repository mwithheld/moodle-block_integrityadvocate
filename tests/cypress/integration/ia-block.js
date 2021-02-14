/// <reference types="cypress" />
// Refs:
// - Code re-use: https://stackoverflow.com/questions/59008563/cypress-re-use-auth-token-across-multiple-api-tests

//require('cypress-xpath');
//require('cypress-file-upload');
//require('cypress-iframe');
// ref https://github.com/javierbrea/cypress-fail-fast
import 'cypress-fail-fast';

//-----------------------------------------------------------------------------
//#region Global constants and vars
//-----------------------------------------------------------------------------
// Base URL with no trailing slash.
Cypress.config('baseUrl', 'http://127.0.0.1/moodle');
const strings = {
  function_delimiter: '--',
  username_admin: 'user',
  password_admin: 'bitnami',
  appid: '2b5cdd71-aeb1-4f3d-8ac0-1f3acca4efe4',
  apikey: 'c5oNspfrqaUuYX+3/Res/7/8VnxS385tlmqoU4/bVcA=',

  baseurl: Cypress.config().baseUrl,
  coursename: 'ia-automated-tests',
  block_fullname: 'Integrity Advocate',
  block_shortname: 'block_integrityadvocate',
}
const urls = {
  baseurl: strings.baseurl,
  home: '/',
  login: '/login/',

  course_home: '/course/view.php?name=' + strings.coursename,
  course_management: '/course/management.php'
}

/**
 * Suppress logging of xhr requests.
 * @url https://docs.cypress.io/api/commands/server.html#Options
 */
Cypress.Server.defaults({
  ignore: (xhr) => {
    return true;
  }
});
//#endregion

//-----------------------------------------------------------------------------
//#region Custom commands re-usable across this app.
//-----------------------------------------------------------------------------
/**
 * Moodle login.
 * 
 * @param {string} url The Moodle base url.
 * @param {string} username The username to login as.
 * @param {string} password The password to use.
 * @returns {object} Whatever cy.request() returns = the response object literal.
 */
Cypress.Commands.add('login', (url, username, password) => {
  const debug = false;
  debug && cy.log("login::Started with url.login=", url);
  return cy.request(url, { log: false })
    .its('body')
    .then(body => {
      // we can use Cypress.$ to parse the string body
      // thus enabling us to query into it easily
      const html = Cypress.$(body);
      const csrfToken = html.find('input[name=logintoken]').val();

      cy.request({
        method: 'POST',
        url: url,
        form: true, // Indicates the body should be form urlencoded and sets Content-Type: application/x-www-form-urlencoded headers.
        body: {
          username: username,
          password: password,
          logintoken: csrfToken,
        },
      }).then(resp => {
        debug && expect(resp.status).to.eq(200);
        debug && expect(resp.body).to.include('You are logged in as');
        cy.location('href').should('not.include', '/login');
      });
    });
});

/**
 * If it is not already, turn on course editing mode.
 * 
 * @returns {object} Whatever cy.get('body') returns =  the DOM element it found.
 */
Cypress.Commands.add('course_editing_on', () => {
  const debug = false;
  debug && cy.log('course_editing_on::Started');

  // Enter course editing mode.
  var returnThis = cy.get('body').then(body => {
    if (body.hasClass('editing')) {
      debug && cy.log('course_editing_on::course editing mode is already on');
    } else {
      cy.get('#page-header button').contains('Turn editing on').trigger('mouseover').click({ force: true }).then(e => {
        cy.location('href').should('contains', 'notifyeditingon=1');
      });
    }
  });
  debug && cy.log('course_editing_on::Done');
  return returnThis;
});

/**
 * If it is not already, open the nav drawer (left-hand menu).
 * 
 * @returns {object} Whatever cy.get('body') returns =  the DOM element it found.
 */
Cypress.Commands.add('navdrawer_open', () => {
  const debug = false;
  debug && cy.log('navdrawer_open::Started');

  // If the navdrawer is closed, open it.
  return cy.get('body').then(body => {
    if (body.find('div#nav-drawer.closed').length > 0) {
      debug && cy.log('navdrawer_open::sidebar is closed');
      cy.get('button[data-preference=drawer-open-nav').trigger('mouseover').click().then(() => {
        debug && cy.log('navdrawer_open::sidebar should now be opened');
        cy.get("div#nav-drawer").should('not.have.class', 'closed');
      });
    } else {
      debug && cy.log('navdrawer_open::navdrawer is already open');
    }
  });
});
//#endregion

//-----------------------------------------------------------------------------
//#region Cypress-based functions specific to this spec.
//-----------------------------------------------------------------------------
/**
 * Make sure course editing is on and remove any existing IA blocks.
 * 
 * @param {bool} removeCourseBlock True to remove the course-level block.
 * @param {bool} removeQuizBlock True to remove the quiz-level block.
 * @returns Whatever course_editing_on() returns.
 */
const block_ia_test_prep = (removeCourseBlock = true, removeQuizBlock = true) => {
  const fxn = 'block_ia_test_prep';
  cy.log(strings.function_delimiter + fxn + '::Started with removeCourseBlock=' + removeCourseBlock + '; removeQuizBlock=' + removeQuizBlock);

  var returnThis = cy.course_editing_on().then(() => {
    removeCourseBlock && block_ia_remove();
    removeQuizBlock && block_ia_remove_from_quiz();
  });

  cy.log(strings.function_delimiter + fxn + '::Done');
  return returnThis;
};

/**
 * Return true if the current url is the test course home. 
 * @url https://dmitripavlutin.com/parse-url-javascript/
 *
 * @param {string} url URL to test.
 * @returns {bool} True if the current url is the test course home.
 */
const block_ia_is_url_course_home = (url) => {
  const debug = false;
  const fxn = 'block_ia_is_url_course_home';
  cy.log(strings.function_delimiter + fxn + '::Started with url=' + (typeof url !== 'undefined' ? '' : url));

  debug && cy.log(strings.function_delimiter + fxn + '::About to compare input url=' + new URL(url).pathname + ' vs urls.course_home=' + new URL(urls.baseurl + urls.course_home).pathname);
  var returnThis = new URL(url).pathname == new URL(urls.baseurl + urls.course_home).pathname;

  cy.log(strings.function_delimiter + fxn + '::Done');
  return returnThis;
}

/**
* Add the IA block to the current page.
* Assumes we are already on the target page, course editing mode is on.
*
* @returns {object} Whatever cy.get('#nav-drawer span') returns = the DOM element it found.
*/
const block_ia_add_to_page = () => {
  const debug = false;
  const fxn = 'block_ia_add_to_page';
  cy.log(strings.function_delimiter + fxn + '::Started');

  cy.navdrawer_open();
  var returnThis = cy.get('#nav-drawer span').contains('Add a block').click().then(() => {
    cy.get('.list-group-item-action', { timeout: 10000 }).should('contain', strings.block_fullname).then((eltouter) => {
      cy.wrap(eltouter).contains(strings.block_fullname).then(elt => {
        cy.wrap(elt).click().then(() => {
          cy.get('body').then(body => {
            cy.wrap(body).find('section.block_integrityadvocate').should('have.length', 1);
          });
        });
      });
    });
  });

  cy.log(strings.function_delimiter + fxn + '::Done');
  return returnThis;
};

/**
 * Does this:
 * - Enable course editing
 * - Add the IA block to the course page
 * - If(do_configure) then 
 *   - Assert the block was added with no config
 *   - Configure the block
 * Does *not* assert the block got configured.
 * Assumes we are on the course home page.
 * 
 * @param {bool} do_configure True to configure the block.
 */
const block_ia_add_to_course = (do_configure = true) => {
  const debug = false;
  const fxn = 'block_ia_add_to_course';
  cy.log(strings.function_delimiter + fxn + '::Started with do_configure=' + do_configure);

  var returnThis = null;

  cy.course_editing_on().then(() => {
    debug && cy.log(strings.function_delimiter + fxn + '::Step: Add the block to the course');
    returnThis = block_ia_add_to_page();
  });

  if (do_configure) {
    debug && cy.log(strings.function_delimiter + fxn + '::Step: Make sure the course block was added with no config');
    cy.get('body').then(body => {
      cy.wrap(body).find('.block_integrityadvocate').then(elt => {
        block_ia_assert_instructor_course_view({ elt: elt });
      });
    });

    debug && cy.log(strings.function_delimiter + fxn + '::Step: Configure the course block and assert it got configured');
    returnThis = block_ia_configure(strings.appid, strings.apikey);
  }

  cy.log(strings.function_delimiter + fxn + '::Done');
  return returnThis;
}

/**
 * Assert the block element looks the way an instructor should see it.
 * 
 * @param {object} object where:
 *    {jQuery<HtmlElement>} elt HTML element of the block.
 *    {bool} configured True if the block should have been configured.
 *    {bool} isModuleLevel True if the block is at module-level and not course-level.
 * @return {object} Whatever cy.wrap(elt) returns = the DOM element it found.
 */
const block_ia_assert_instructor_course_view = (
  { elt = null, configured = false, isModuleLevel = false }
) => {
  const debug = false;
  const fxn = 'block_ia_assert_instructor_course_view';
  cy.log(strings.function_delimiter + fxn + '::Started with configured=' + configured + '; isModuleLevel=' + isModuleLevel);

  // There should only be one block.
  cy.wrap(elt).should('have.length', 1);
  block_ia_assert_footer(elt, configured);

  if (configured) {
    if (!isModuleLevel) {
      // We should see the Course Overview button.
      cy.wrap(elt).find('button[type=submit]').contains('Course Overview');

      // We should see a Course link with a link to the current course URL.
      cy.wrap(elt).find('.block_integrityadvocate_modulelist_div a').contains('Course').then(e => {
        cy.location('href').then(url => {
          expect(e).to.have.attr('href', url);
        });
      });
    } else {
      // We should see the Course Overview button.
      cy.wrap(elt).find('button[type=submit]').contains('Course Overview');
      // We should see the Module Overview button.
      cy.wrap(elt).find('button[type=submit]').contains('Module Overview');

    }
  } else {
    block_ia_assert_content_no_config(elt);
  }
  var returnThis = cy.wrap(elt);

  cy.log(strings.function_delimiter + fxn + '::Done');
  return returnThis;
}

/**
 * Assert the block footer looks how it should.
 * 
 * @param {jQuery<HtmlElement>} elt HTML element of the block.
 * @param {bool} configured True if the block should have been configured.
 * @return {object} Whatever cy.wrap(elt) returns = the DOM element it found.
 */
const block_ia_assert_footer = (elt, configured = false) => {
  const debug = false;
  const fxn = 'block_ia_assert_footer';
  cy.log(strings.function_delimiter + fxn + '::Started with configured=' + configured);

  // We should see Application Id.
  debug && cy.log(strings.function_delimiter + fxn + '::Step: Make sure the Course link is correct');
  cy.wrap(elt).contains('Application Id ' + (configured ? strings.appid : ''));

  // We should see version info.
  var returnThis = cy.wrap(elt).find('.block_integrityadvocate_plugininfo[title=Version]').should(e => {
    expect(e).to.have.length(1);
  });

  cy.log(strings.function_delimiter + fxn + '::Done');
  return returnThis;
}

/**
 * Assert the block content looks how it should.
 * 
 * @param {jQuery<HtmlElement>} elt HTML element of the block.
 * @return {object} Whatever cy.wrap(elt) returns = the DOM element it found.
 */
const block_ia_assert_content_no_config = (elt) => {
  const debug = false;
  const fxn = 'block_ia_assert_content_no_config';
  cy.log(strings.function_delimiter + fxn + '::Started');

  var returnThis = cy.wrap(elt).should('contain', 'This block has no config')
    .should('contain', 'No Api key is set')
    .should('contain', 'No Application Id is set');

  cy.log(strings.function_delimiter + fxn + '::Done');
  return returnThis;
}

/**
 * Click into the quiz and add the block and configure it.
 * 
 * @returns {object} Whatever cy.get('.section .modtype_quiz') returns = the DOM element it found.
 */
const block_ia_add_to_quiz = () => {
  const debug = false;
  const fxn = 'block_ia_add_to_quiz';
  cy.log(strings.function_delimiter + fxn + '::Started');
  debug && cy.log(strings.function_delimiter + fxn + '::Step: Click into the quiz and add the block');
  var returnThis = cy.get('.section .modtype_quiz').first().find('a.aalink span.instancename').trigger('mouseover').click().then(() => {
    cy.url().should('include', '/mod/quiz/view.php');
    cy.get('#nav-drawer span').should('contain', 'Add a block');

    debug && cy.log(strings.function_delimiter + fxn + '::Step: Add the block to the quiz');
    cy.get('body').then(body => {
      if (body.find('.block_integrityadvocate').length < 1) {
        block_ia_add_to_page();
        block_ia_configure(strings.appid, strings.apikey);
      }
    });
  });

  cy.log(strings.function_delimiter + fxn + '::Done');
  return returnThis;
}

/**
 * On the current page, configure the IA block with the params passed in, then assert it worked.
 * 
 * @param {string} appid 
 * @param {string} apikey 
 * @returns {object} Whatever cy.get('.block_integrityadvocate') returns = the DOM element it found.
 */
const block_ia_configure = (appid, apikey) => {
  const debug = false;
  const fxn = 'block_ia_configure';
  cy.log(strings.function_delimiter + fxn + '::Started with appid=' + appid + '; apikey=' + apikey);

  var returnThis = cy.get('.block_integrityadvocate').as('block_integrityadvocate').find('.action-menu .dropdown-toggle.icon-no-margin').click().then(() => {
    cy.get('@block_integrityadvocate').find('.dropdown-menu .editing_edit').click({ force: true }).then(() => {
      cy.get('body').should('contain', 'Block settings');

      cy.get('#id_config_appid').type(appid);
      cy.get('#id_config_apikey').type(apikey);

      cy.get('#id_submitbutton').click().then(() => {
        cy.get('@block_integrityadvocate').then(elt => {
          cy.location('href').then(url => {
            block_ia_assert_instructor_course_view({ elt: elt, configured: true, isModuleLevel: !block_ia_is_url_course_home(url) });
          });
        });
      });
    });
  });

  cy.log(strings.function_delimiter + fxn + '::Done');
  return returnThis;
};

/**
 * Assumes we are already on the course home page, and course editing mode is on.
 */
const block_ia_remove = () => {
  const debug = false;
  const fxn = 'block_ia_remove';
  cy.log(strings.function_delimiter + fxn + '::Started');

  var returnThis = cy.get('body').then(body => {
    if (body.find('.block_integrityadvocate').length > 0) {
      debug && cy.log(strings.function_delimiter + fxn + '::Found an IA block, so delete it');
      cy.get('.block_integrityadvocate').as('block_integrityadvocate').find('.action-menu a.dropdown-toggle').click().then(() => {
        cy.get('@block_integrityadvocate').find('.dropdown-menu .editing_delete').click({ force: true });
        // Confirm delete.
        cy.get('#modal-footer button.btn-primary').trigger('mouseover').click().then(() => {
          cy.get('body').find('.block_integrityadvocate').should('not.exist');
        });
        debug && cy.log(strings.function_delimiter + fxn + '::Deleted the existing IA block');
      });
    }
  });

  cy.log(strings.function_delimiter + fxn + '::Done');
  return returnThis;
};

/**
* Click into the quiz and remove any existing IA block.
* Assumes we are already on the course home page, course editing mode is on.
*/
const block_ia_remove_from_quiz = () => {
  const debug = false;
  const fxn = 'block_ia_remove_from_quiz';
  cy.log(strings.function_delimiter + fxn + '::Started');

  var returnThis = cy.get('.section .modtype_quiz').first().find('a.aalink span.instancename').scrollIntoView().click().then(() => {
    cy.location('href').should('include', '/mod/quiz/view.php');
    cy.get('#nav-drawer span').should('contain', 'Add a block');
    block_ia_remove();
  });

  cy.log(strings.function_delimiter + fxn + '::Done');
  return returnThis;
};
//#endregion

//-----------------------------------------------------------------------------
//#region Test suite begins.
//-----------------------------------------------------------------------------
describe('block_ia-testsuite', () => {
  // Run once before all tests in the block.
  // Setup done this way is an anti-pattern, but can't be done properly if we are switching b/t Moodles on different servers.
  before(() => {
    const debug = false;
    debug && cy.log('before::Started');

    // Optionally disable all before() actions.
    if (true) {
      cy.login(strings.baseurl + urls.login, strings.username_admin, strings.password_admin);

      debug && cy.log('before::Step: Check if we should delete the old course');
      const targeturl = urls.course_management + '?search=' + strings.coursename;
      cy.visit(targeturl).then(() => {
        cy.get('body').then(body => {
          if (body.find(".course-listing .listitem-course:contains('" + strings.coursename + "')").length > 0) {
            debug && cy.log('before::test course exists');
            // Hit the delete trash can link.
            cy.get(" .listitem-course:contains('" + strings.coursename + "')").find('a.action-delete').click();
            // Confirm delete.
            cy.get('#modal-footer button.btn-primary').click();
          } else {
            debug && cy.log('before::test course does not exist');
          }
        });
      });

      debug && cy.log('before::Step: Create a new empty course to hold the test course content');
      cy.visit(urls.course_management).then(() => {
        cy.get('.course-listing-actions > a.btn').click().then(() => {
          cy.get('#id_fullname').type(strings.coursename);
          cy.get('#id_shortname').type(strings.coursename);
          cy.get('#id_enddate_enabled').uncheck();
          cy.get('#id_saveanddisplay').click();
        });
      });
      cy.get('.breadcrumb').should('contain', strings.coursename);

      // Restore the test course into the Misc category id=1.
      // Skip: I could not get this to work.
      //cy.visit('/backup/restorefile.php?contextid=1');
      //cy.get('.filepicker-container').last().attachFile('ia-automated-tests.mbz', { subjectType: 'drag-n-drop', force: true, mimeType: 'application/octet-stream' });

      // ASSUME the course is already in the admin profile user private backup area.
      cy.visit('/backup/restorefile.php?contextid=1');
      cy.get(".backup-files-table:contains('" + strings.coursename + "')").find('a:contains("Restore")').click();
      cy.get('.backup-restore button[type=submit]').click();
      cy.url().should('contains', '/backup/restore.php');

      cy.get('.bcs-existing-course #detail-pair-value-3').check();
      cy.get(".bcs-existing-course .restore-course-search tr:contains('" + strings.coursename + "')").find('input').check();
      cy.get('.bcs-existing-course input[type="Submit"]').click();
      cy.url().should('contains', '/backup/restore.php');

      cy.get('#id_submitbutton').click();
      cy.url().should('contains', '/backup/restore.php');
      cy.get('#id_submitbutton').click();
      cy.url().should('contains', '/backup/restore.php');
      cy.get('#id_submitbutton').click();
      cy.url().should('contains', '/backup/restore.php');
      cy.get('.continuebutton button').click();
      cy.url().should('contains', '/course/view.php?id=');

      debug && cy.log('before::Done');
    }
  });

  // Runs before each test in this test suite.
  beforeEach(() => {
    const debug = false;
    debug && cy.log('beforeEach::Started');

    cy.login(strings.baseurl + urls.login, strings.username_admin, strings.password_admin);
    debug && cy.log('beforeEach::Done');
  });

  afterEach(() => {
    cy.window().then(win => {
      if (win.gc) {
        gc();
        gc();
        gc();
        gc();
        gc();
        cy.wait(1000)
      }
    })
  })

  it('can-add-block-to-course-and-config', function () {
    cy.visit(urls.course_home).then(() => {
      block_ia_add_to_course(false);
    });
  });

  it('cannot-add-block-with-bad-config', function () {
    cy.visit(urls.course_home).then(() => {
      block_ia_test_prep();

      cy.course_editing_on().then(() => {
        cy.log(this.test.title + '::Step: Add the block to the course');
        block_ia_add_to_page();
      });
    });

    cy.log(this.test.title + '::Step: Edit the block config');
    cy.get('.block_integrityadvocate').find('.action-menu .dropdown-toggle').click().then(() => {
      cy.get('.block_integrityadvocate').find('.dropdown-menu .editing_edit').click({ force: true });
    });
    cy.get('body').should('contain', 'Block settings');

    cy.log(this.test.title + '::Step: Test a bad appid shows an error');
    cy.get('#id_config_appid').type('some-bad-appid');
    cy.get('#id_submitbutton').click().then(() => {
      cy.get('#id_config_appid').closest('form').find('.invalid-feedback').should('contain', 'Invalid Application Id');
    });

    // Set the appid to a good value so it does not show up as a validation error.
    cy.get('#id_config_appid').clear().type(strings.appid);

    cy.log(this.test.title + '::Step: Test a bad apikey shows an error');
    cy.get('#id_config_apikey').type('some-bad-apikey');
    cy.get('#id_submitbutton').click().then(() => {
      cy.get('#id_config_appid').closest('form').find('.invalid-feedback').should('contain', 'Invalid API Key');
    });

    cy.log(this.test.title + '::Step: Test good values work');
    cy.get('#id_config_apikey').clear().type(strings.apikey);
    cy.get('#id_submitbutton').click().then(() => {
      cy.get('.block_integrityadvocate').then(elt => {
        cy.url().then(url => {
          block_ia_assert_instructor_course_view({ elt: elt, configured: true, isModuleLevel: !block_ia_is_url_course_home(url) });
        });
      });
    });
  });

  it('can-add-block-to-quiz-and-config', function () {
    cy.visit(urls.course_home).then(() => {
      cy.log(this.test.title + '::Step: Make sure course editing is on and remove any existing IA blocks');
      block_ia_test_prep();

      cy.log(this.test.title + '::Step: Add the block to the quiz');
      block_ia_add_to_page();

      cy.log(this.test.title + '::Step: Make sure the block was added to the quiz with no config');
      cy.get('body').then(body => {
        cy.wrap(body).find('.block_integrityadvocate').as('block_integrityadvocate').then(elt => {
          block_ia_assert_instructor_course_view({ elt: elt, configured: false, isModuleLevel: true });
        });
      });

      cy.log(this.test.title + '::Step: Configure the quiz block and assert it got configured');
      block_ia_configure(strings.appid, strings.apikey);

      // We should see the Module Overview button.
      cy.get('@block_integrityadvocate').find('button[type=submit]').contains('Module Overview');
    });
  });

  it('add-block-to-course-then-quiz-should-pick-up-config', function () {
    cy.visit(urls.course_home).then(() => {
      cy.log(this.test.title + '::Step: Make sure course editing is on and remove any existing IA blocks');
      // Don't bother removing the course-level block - we're just gonna add it again.
      block_ia_test_prep(false, true);
    });

    cy.visit(urls.course_home).then(() => {
      cy.log(this.test.title + '::Step: Add the course block');
      block_ia_add_to_course();

      cy.log(this.test.title + '::Step: Click into the quiz');
      cy.get('.section .modtype_quiz').first().find('a.aalink span.instancename').trigger('mouseover').click().then(() => {
        cy.url().should('include', '/mod/quiz/view.php');
        cy.get('#nav-drawer span').should('contain', 'Add a block');
      });

      cy.log(this.test.title + '::Step: Add the block to the quiz');
      block_ia_add_to_page();

      cy.log(this.test.title + '::Step: Check the quiz block picked up the config from the course-level IA block');
      cy.get('.block_integrityadvocate').then(elt => {
        block_ia_assert_instructor_course_view({ elt: elt, configured: true, isModuleLevel: true });
      });
    });
  });

  it('add-block-to-quiz-then-course-should-pick-up-config', function () {
    cy.visit(urls.course_home).then(() => {
      cy.log(this.test.title + '::Step: Make sure course editing is on and remove any existing IA blocks');
      // Don't bother removing the quiz-level block - we're just gonna add it again.
      block_ia_test_prep(true, false);
    });

    cy.visit(urls.course_home).then(() => {
      cy.log(this.test.title + '::Step: Add the course block');
      block_ia_add_to_course(false);

      cy.log(this.test.title + '::Step: Check the course block picked up the config from the quiz-level IA block');
      cy.get('.block_integrityadvocate').then(elt => {
        block_ia_assert_instructor_course_view({ elt: elt, configured: true, isModuleLevel: false });
      });
    });
  });

  // it('two-blocks-in-quiz-should-hide-second', function () {
  // });

  // it('certificate-add-ia-restriction', function () {
  // });
});
//#endregion