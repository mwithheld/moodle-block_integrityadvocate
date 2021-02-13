/// <reference types="cypress" />
// Refs:
// - Code re-use: https://stackoverflow.com/questions/59008563/cypress-re-use-auth-token-across-multiple-api-tests

//require('cypress-xpath');
//require('cypress-file-upload');
//require('cypress-iframe');

//-----------------------------------------------------------------------------
// Global constants and vars.
//-----------------------------------------------------------------------------
// Base URL with no trailing slash.
Cypress.config('baseUrl', 'http://127.0.0.1/moodle');
const strings = {
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

//-----------------------------------------------------------------------------
// Custom commands re-usable across this app.
//-----------------------------------------------------------------------------
//#region 
Cypress.Commands.add('login', (url, username, password) => {
  const debug = false;
  debug && cy.log("login::Started with url.login=", url);
  cy.request(url, { log: false })
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
        cy.url().should('not.include', '/login');
      });
    });
});

Cypress.Commands.add('course_editing_on', () => {
  const debug = false;
  debug && cy.log('course_editing_on::Started');

  // Enter course editing mode.
  cy.get('body').then(body => {
    if (body.hasClass('editing')) {
      debug && cy.log('course_editing_on::course editing mode is already on');
    } else {
      cy.get('#page-header button').contains('Turn editing on').trigger('mouseover').click().then(e => {
        cy.url().should('contains', 'notifyeditingon=1');
      });
    }
  });
  debug && cy.log('course_editing_on::Done');
});

Cypress.Commands.add('navdrawer_open', () => {
  const debug = false;
  debug && cy.log('navdrawer_open::Started');

  // If the navdrawer is closed, open it.
  cy.get('body').then(body => {
    if (body.find('div#nav-drawer.closed').length > 0) {
      debug && cy.log('navdrawer_open::sidebar is closed');
      cy.get('button[data-preference=drawer-open-nav').click().then(() => {
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
// Cypress-based functions specific to this spec.
//-----------------------------------------------------------------------------
//#region 

/**
 * Make sure course editing is on and remove any existing IA blocks
 * 
 * @param {*} removeCourseBlock 
 * @param {*} removeQuizBlock 
 */
const test_prep = (removeCourseBlock = true, removeQuizBlock = true) => {
  const debug = false;
  debug && cy.log('test_prep::Started with removeCourseBlock=' + removeCourseBlock + '; removeQuizBlock=' + removeQuizBlock);

  cy.course_editing_on().then(() => {
    removeCourseBlock && block_ia_remove();
    removeQuizBlock && quiz_block_ia_remove();
  });
};

/**
 * @url https://dmitripavlutin.com/parse-url-javascript/
 */
const url_is_course_home = (url) => {
  const debug = false;
  debug && cy.log('url_is_course_home::Started with url=' + (typeof url !== 'undefined' ? '' : url));

  debug && cy.log('url_is_course_home::About to compare input url=' + new URL(url).pathname + ' vs urls.course_home=' + new URL(urls.baseurl + urls.course_home).pathname);
  return new URL(url).pathname == new URL(urls.baseurl + urls.course_home).pathname;
}

/**
* Add the IA block to the current page.
* Assumes we are already on the target page, course editing mode is on.
*/
const block_ia_add = () => {
  const debug = false;
  debug && cy.log('block_ia_add::Started');

  cy.navdrawer_open();
  cy.get('#nav-drawer span').contains('Add a block').click();
  return cy.get('.list-group-item-action', { timeout: 10000 }).should('contain', strings.block_fullname).then((eltouter) => {
    cy.wrap(eltouter).contains(strings.block_fullname).then(elt => {
      cy.wrap(elt).click().then(() => {
        cy.get('body').then(body => {
          cy.wrap(body).find('section.block_integrityadvocate').should('have.length', 1);
        });
      });
    });
  });
};

/**
 * Does this:
 * - Enable course editing
 * - Delete any existing course-level block
 * - Add the IA block to the course page
 * - If(do_configure) then 
 *   - Assert the block was added with no config
 *   - Configure the block
 * Does *not* assert the block got configured
 * Assumes we are on the course home page.
 * 
 * @param {*} do_configure 
 */
const course_block_add = (do_configure = true) => {
  const debug = false;
  debug && cy.log('course_block_add::Started with do_configure=' + do_configure);

  debug && cy.log('course_block_add::Step: Delete any existing course-level IA block');
  cy.course_editing_on().then(() => {
    block_ia_remove();
  });

  debug && cy.log('course_block_add::Step: Add the block to the course');
  block_ia_add();

  if (do_configure) {
    debug && cy.log('course_block_add::Step: Make sure the course block was added with no config');
    cy.get('body').then(body => {
      cy.wrap(body).find('.block_integrityadvocate').then(elt => {
        course_block_ia_assert_instructor_view({ elt: elt });
      });
    });

    debug && cy.log('course_block_add::Step: Configure the course block and assert it got configured');
    block_ia_configure(strings.appid, strings.apikey);
  }
}

/**
 * Assert the block element looks the way an instructor should see it.
 * 
 * @param {object} object where:
 *    jQuery<HtmlElement> elt HTML element of the block
 *    bool configured True if the block should have been configured.
 *    bool isModuleLevel True if the block is at module-level and not course-level.
 */
const course_block_ia_assert_instructor_view = (
  { elt = null, configured = false, isModuleLevel = false}
) => {
  const debug = false;
  debug && cy.log('course_block_ia_assert_instructor_view::Started with configured=' + configured + '; isModuleLevel=' + isModuleLevel);

  // There should only be one block.
  cy.wrap(elt).should('have.length', 1);
  block_ia_assert_footer(elt, configured);

  if (configured) {
    if (!isModuleLevel) {
      // We should see the Course Overview button.
      cy.wrap(elt).find('button[type=submit]').contains('Course Overview');

      // We should see a Course link with a link to the current course URL.
      cy.wrap(elt).find('.block_integrityadvocate_modulelist_div a').contains('Course').then(e => {
        cy.url().then(url => {
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
}

const block_ia_assert_footer = (elt, configured = false) => {
  const debug = false;
  debug && cy.log('block_ia_assert_footer::Started with configured=' + configured);

  // We should see Application Id.
  debug && cy.log('block_ia_assert_footer::Step: Make sure the Course link is correct');
  cy.wrap(elt).contains('Application Id ' + (configured ? strings.appid : ''));

  // We should see version info.
  return cy.wrap(elt).find('.block_integrityadvocate_plugininfo[title=Version]').should(e => {
    expect(e).to.have.length(1);
  });
}

const block_ia_assert_content_no_config = (elt) => {
  const debug = false;
  debug && cy.log('block_ia_assert_footer::Started');

  expect(elt).to.contain('This block has no config');
  expect(elt).to.contain('No Api key is set');
  expect(elt).to.contain('No Application Id is set');
  return cy;
}

/**
 * On the current page, configure the IA block with the params passed in, then assert it worked.
 * 
 * @param {*} appid 
 * @param {*} apikey 
 */
const block_ia_configure = (appid, apikey) => {
  const debug = false;
  debug && cy.log('block_ia_configure::Started with appid=' + appid + '; apikey=' + apikey);

  return cy.get('.block_integrityadvocate').as('block_integrityadvocate').find('.action-menu .dropdown-toggle.icon-no-margin').click().then(() => {
    cy.get('@block_integrityadvocate').find('.dropdown-menu .editing_edit').click({ force: true }).then(() => {
      cy.get('body').should('contain', 'Block settings');

      cy.get('#id_config_appid').type(appid);
      cy.get('#id_config_apikey').type(apikey);

      cy.get('#id_submitbutton').click().then(() => {
        cy.get('@block_integrityadvocate').then(elt => {
          cy.url().then(url => {
            course_block_ia_assert_instructor_view({ elt: elt, configured: true, isModuleLevel: !url_is_course_home(url) });
          });
        });
      });
    });
  });
};

/**
 * Assumes we are already on the course home page, and course editing mode is on.
 */
const block_ia_remove = () => {
  const debug = false;
  debug && cy.log('block_ia_remove::Started');

  return cy.get('body').then(body => {
    if (body.find('.block_integrityadvocate').length > 0) {
      debug && cy.log('block_ia_remove::Found an IA block, so delete it');
      cy.get('.block_integrityadvocate').as('block_integrityadvocate').find('.action-menu a.dropdown-toggle').click().then(() => {
        cy.get('@block_integrityadvocate').find('.dropdown-menu .editing_delete').click({ force: true });
        // Confirm delete.
        cy.get('#modal-footer button.btn-primary').click().then(() => {
          cy.get('body').find('.block_integrityadvocate').should('not.exist');
        });
        debug && cy.log('block_ia_remove::Deleted the existing IA block');
      });
    }
  });
};

/**
* Click into the quiz and remove any existing IA block.
* Assumes we are already on the course home page, course editing mode is on.
*/
const quiz_block_ia_remove = () => {
  const debug = false;
  debug && cy.log('quiz_block_ia_remove::Started');

  cy.get('.section .modtype_quiz').first().find('a.aalink span.instancename').scrollIntoView().click().then(() => {
    cy.url().should('include', '/mod/quiz/view.php');
    cy.get('#nav-drawer span').should('contain', 'Add a block');
    block_ia_remove();
  });
};
//#endregion

//-----------------------------------------------------------------------------
// Test suite begins.
//-----------------------------------------------------------------------------
describe('ia-block-testsuite', () => {
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
      course_block_add();
    });
  });

  // it.only('cannot-add-block-with-bad-config', function () {
  //   cy.visit(urls.course_home).then(() => {
  //     test_prep();
  //   });
  // });

  it('can-add-block-to-quiz-and-config', function () {
    cy.visit(urls.course_home).then(() => {
      cy.log(this.test.title + '::Step: Make sure course editing is on and remove any existing IA blocks');
      test_prep();

      cy.log(this.test.title + '::Step: Add the block to the quiz');
      block_ia_add();

      cy.log(this.test.title + '::Step: Make sure the block was added to the quiz with no config');
      cy.get('body').then(body => {
        cy.wrap(body).find('.block_integrityadvocate').as('block_integrityadvocate').then(elt => {
          course_block_ia_assert_instructor_view({ elt: elt, configured: false, isModuleLevel: true });
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
      test_prep(false, true);
    });

    cy.visit(urls.course_home).then(() => {
      cy.log(this.test.title + '::Step: Add the course block');
      course_block_add();

      cy.log(this.test.title + '::Step: Click into the quiz');
      cy.get('.section .modtype_quiz').first().find('a.aalink span.instancename').trigger('mouseover').click().then(() => {
        cy.url().should('include', '/mod/quiz/view.php');
        cy.get('#nav-drawer span').should('contain', 'Add a block');
      });

      cy.log(this.test.title + '::Step: Add the block to the quiz');
      block_ia_add();

      cy.log(this.test.title + '::Step: Check the quiz block picked up the config from the course-level IA block');
      cy.get('.block_integrityadvocate').then(elt => {
        course_block_ia_assert_instructor_view({ elt: elt, configured: true, isModuleLevel: true});
      });
    });
  });

  it('add-block-to-quiz-then-course-should-pick-up-config', function () {
    cy.visit(urls.course_home).then(() => {
      cy.log(this.test.title + '::Step: Make sure course editing is on and remove any existing IA blocks');
      // Don't bother removing the quiz-level block - we're just gonna add it again.
      test_prep(true, false);

      cy.log(this.test.title + '::Step: Click into the quiz and add the block');
      cy.get('.section .modtype_quiz').first().find('a.aalink span.instancename').trigger('mouseover').click().then(() => {
        cy.url().should('include', '/mod/quiz/view.php');
        cy.get('#nav-drawer span').should('contain', 'Add a block');

        cy.log(this.test.title + '::Step: Add the block to the quiz');
        cy.get('body').then(body => {
          if (body.find('.block_integrityadvocate').length < 1) {
            block_ia_add();
            block_ia_configure(strings.appid, strings.apikey);
          }
        });
      });
    });

    cy.visit(urls.course_home).then(() => {
      cy.log(this.test.title + '::Step: Add the course block');
      course_block_add(false);

      cy.log(this.test.title + '::Step: Check the course block picked up the config from the quiz-level IA block');
      cy.get('.block_integrityadvocate').then(elt => {
        course_block_ia_assert_instructor_view({ elt: elt, configured: true, isModuleLevel: false });
      });
    });
  });

  // it('two-blocks-in-quiz-should-hide-second', function () {
  // });

  // it('certificate-add-ia-restriction', function () {
  // });
});