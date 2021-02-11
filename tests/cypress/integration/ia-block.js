// Refs:
// - Code re-use: https://stackoverflow.com/questions/59008563/cypress-re-use-auth-token-across-multiple-api-tests

// require('cypress-xpath');
//require('cypress-file-upload');
//require('cypress-iframe');

// Base URL with no trailing slash.
Cypress.config('baseUrl', 'http://127.0.0.1/moodle');
var strings = {
  username_admin: 'user',
  password_admin: 'bitnami',
  appid: '***REMOVED***',
  apikey: '***REMOVED***',

  baseurl: Cypress.config().baseUrl,
  coursename: 'ia-automated-tests',
  block_fullname: 'Integrity Advocate',
  block_shortname: 'block_integrityadvocate',
}
var url = {
  home: '/',
  login: '/login/',

  course_home: '/course/view.php?name=' + strings.coursename,
  course_management: '/course/management.php'
}

if (true) {
  Cypress.Commands.add('login', (url, username, password) => {
    cy.log("login::Started with url.login=", url);
    cy.request(url)
      .its('body')
      .then(body => {
        // we can use Cypress.$ to parse the string body
        // thus enabling us to query into it easily
        const $html = Cypress.$(body);
        const csrfToken = $html.find('input[name=logintoken]').val();

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
          expect(resp.status).to.eq(200);
          expect(resp.body).to.include('You are logged in as');
          cy.url().should('not.include', '/login');
        });
      });
  });

  Cypress.Commands.add('course_editing_on', () => {
    // Enter course editing mode.
    cy.get('body').then(body => {
      if (body.find('body.editing').length > 0) {
        cy.log('course_editing_on::course editing mode is already on');
      } else {
        cy.get('#page-header button').contains('Turn editing on').click().then(e => {
          cy.url().should('contains', 'notifyeditingon=1');
        });
      }
    });
  });

  Cypress.Commands.add('block_ia_configure', (appid, apikey) => {
    cy.get('.block_integrityadvocate .action-menu .dropdown-toggle i').scrollIntoView().click().then(() => {
      cy.get('.block_integrityadvocate .action-menu').contains('Configure Integrity Advocate block').click().then(() => {
        cy.get('body').then(body => {
          expect(body).to.contain('Block settings');
        });
        cy.get('#id_config_appid').type(appid);
        cy.get('#id_config_apikey').type(apikey);
        cy.get('#id_submitbutton').click().then(() => {
          // Check items that exist in all IA blocks after config.
          cy.get('.block_integrityadvocate').then(elt => {
            expect(elt).to.have.length(1);
            cy.wrap(elt).find('.block_integrityadvocate_plugininfo[title=Version]').should(e => {
              expect(e).to.have.length(1);
            });
            cy.wrap(elt).contains('Application Id ' + strings.appid);
          });
        });
      });
    });
  });

  /**
   * Assumes we are already on the course home page, and course editing mode is on.
   */
  Cypress.Commands.add('block_ia_remove', () => {
    cy.get('body').then(body => {
      if (body.find('.block_integrityadvocate').length > 0) {
        cy.log('block_ia_remove::Found an IA block, so delete it');
        cy.get('.block_integrityadvocate .action-menu .dropdown-toggle i').scrollIntoView().click().then(() => {
          cy.get('.block_integrityadvocate .action-menu').contains('Delete Integrity Advocate block').click();
          // Confirm delete.
          cy.get('#modal-footer button.btn-primary').click().then(() => {
            cy.get('body').find('.block_integrityadvocate').should('not.exist');
          });
          cy.log('block_ia_remove::Deleted the existing IA block');
        });
      }
    });
  });

  /**
  * Assumes we are already on the page, course editing mode is on.
  */
  Cypress.Commands.add('block_ia_add', () => {
    cy.navdrawer_open();
    cy.get('#nav-drawer span').contains('Add a block').click();
    cy.get('.list-group-item-action', { timeout: 10000 }).should('contain', strings.block_fullname).then((eltouter) => {
      cy.wrap(eltouter).contains(strings.block_fullname).scrollIntoView().then(elt => {
        cy.wrap(elt).click().then(() => {
          cy.get('body').then(body => {
            cy.wrap(body).find('section.block_integrityadvocate').length === 1;
          });
        });
      });
    });
  });

  Cypress.Commands.add('navdrawer_open', () => {
    // If the navdrawer is closed, open it.
    cy.get('body').then(body => {
      if (body.find('div#nav-drawer.closed').length > 0) {
        cy.log('navdrawer_open::sidebar is closed');
        cy.get('button[data-preference=drawer-open-nav').click().then(() => {
          cy.log('navdrawer_open::sidebar should now be opened');
          cy.get("div#nav-drawer").should('not.have.class', 'closed');
        });
      } else {
        cy.log('navdrawer_open::navdrawer is already open');
      }
    });
  });
}

describe('ia-block-testsuite', () => {
  // Run once before all tests in the block.
  // Setup done this way is an anti-pattern, but can't be done properly if we are switching b/t Moodles on different servers.
  // before(() => {
  //   cy.login(strings.baseurl + url.login, strings.username_admin, strings.password_admin);

  //   cy.log('before::Step: Check if we should delete the old course');
  //   let targeturl = url.course_management + '?search=' + strings.coursename;
  //   cy.visit(targeturl).then(contentWindow => {
  //     cy.get('body').then(body => {
  //       if (body.find(".course-listing .listitem-course:contains('" + strings.coursename + "')").length > 0) {
  //         cy.log('before::test course exists');
  //         // Hit the delete trash can link.
  //         cy.get(" .listitem-course:contains('" + strings.coursename + "')").find('a.action-delete i').click();
  //         // Confirm delete.
  //         cy.get('#modal-footer button.btn-primary').click();
  //       } else {
  //         cy.log('before::test course does not exist');
  //       }
  //     });
  //   });

  //   cy.log('before::Step: Create a new empty course to hold the test course content');
  //   cy.visit(url.course_management).then(contentWindow => {
  //     cy.get('.course-listing-actions > a.btn').click().then(() => {
  //       cy.get('#id_fullname').type(strings.coursename);
  //       cy.get('#id_shortname').type(strings.coursename);
  //       cy.get('#id_enddate_enabled').uncheck();
  //       cy.get('#id_saveanddisplay').click();
  //     });
  //   });
  //   cy.get('.breadcrumb').should('contain', strings.coursename);

  //   // Restore the test course into the Misc category id=1.
  //   // Skip: I could not get this to work.
  //   //cy.visit('/backup/restorefile.php?contextid=1');
  //   //cy.get('.filepicker-container').last().attachFile('ia-automated-tests.mbz', { subjectType: 'drag-n-drop', force: true, mimeType: 'application/octet-stream' });

  //   // ASSUME the course is already in the admin profile user private backup area.
  //   cy.visit('/backup/restorefile.php?contextid=1');
  //   cy.get(".backup-files-table:contains('" + strings.coursename + "')").find('a:contains("Restore")').click();
  //   cy.get('.backup-restore button[type=submit]').click();
  //   cy.url().should('contains', '/backup/restore.php');

  //   cy.get('.bcs-existing-course #detail-pair-value-3').check();
  //   cy.get(".bcs-existing-course .restore-course-search tr:contains('" + strings.coursename + "')").find('input').check();
  //   cy.get('.bcs-existing-course input[type="Submit"]').click();
  //   cy.url().should('contains', '/backup/restore.php');

  //   cy.get('#id_submitbutton').click();
  //   cy.url().should('contains', '/backup/restore.php');
  //   cy.get('#id_submitbutton').click();
  //   cy.url().should('contains', '/backup/restore.php');
  //   cy.get('#id_submitbutton').click();
  //   cy.url().should('contains', '/backup/restore.php');
  //   cy.get('.continuebutton button').click();
  //   cy.url().should('contains', '/course/view.php?id=');

  //   cy.log('before::Done');
  // });

  // Runs before each test in this test suite.
  beforeEach(() => {
    cy.login(strings.baseurl + url.login, strings.username_admin, strings.password_admin);
    cy.log('beforeEach::Done');
  });

  it('add-block-to-course', function () {
    cy.visit(url.course_home).then(contentWindow => {
      cy.log(this.test.title + '::Step: Delete any existing course-level IA block');
      cy.course_editing_on().then(contentWindow => {
        cy.block_ia_remove();
      });

      cy.log(this.test.title + '::Step: Add the block to the course');
      cy.block_ia_add();

      cy.log(this.test.title + '::Step: Make sure the course block was added with no config');
      cy.get('body').then(body => {
        cy.wrap(body).find('.block_integrityadvocate').then(elt => {
          expect(elt).to.have.length(1);
          expect(elt).to.contain('This block has no config');
          expect(elt).to.contain('No Api key is set');
          expect(elt).to.contain('No Application Id is set');
        });
      });

      cy.log(this.test.title + '::Step: Configure the course block');
      cy.block_ia_configure(strings.appid, strings.apikey);

      cy.log(this.test.title + '::Step: Check the course block got configured');
      cy.get('.block_integrityadvocate').then(elt => {
        cy.wrap(elt).find('button[type=submit]').contains('Course Overview');

        cy.log(this.test.title + '::Step: Make sure the Course link is correct');
        cy.wrap(elt).find('.block_integrityadvocate_modulelist_div a').contains('Course').then(e => {
          cy.url().then(url => {
            expect(e).to.have.attr('href', url);
          });
        });
      });
    });
  });

  it.only('add-block-to-quiz', function () {
    cy.visit(url.course_home).then(contentWindow => {
      cy.log(this.test.title + '::Step: Make sure course editing is on');
      cy.course_editing_on().then(contentWindow => {
        cy.block_ia_remove();
      });

      cy.log(this.test.title + '::Step: Click into the quiz and check course editing is on');
      cy.get('.section .modtype_quiz').first().find('a.aalink span.instancename').click().then(() => {
        cy.url().should('include', '/mod/quiz/view.php');
        cy.get('#nav-drawer span').should('contain', 'Add a block');
        cy.block_ia_remove();
      });

      cy.log(this.test.title + '::Step: Add the IA block to the quiz');
      cy.block_ia_add();

      cy.log(this.test.title + '::Step: Make sure the block was added to the quiz with no config');
      cy.get('body').then(body => {
        cy.wrap(body).find('.block_integrityadvocate').then(elt => {
          expect(elt).to.have.length(1);
          expect(elt).to.contain('This block has no config');
          expect(elt).to.contain('No Api key is set');
          expect(elt).to.contain('No Application Id is set');
        });
      });

      cy.log(this.test.title + '::Step: Configure the quiz block');
      cy.block_ia_configure(strings.appid, strings.apikey);

      cy.log(this.test.title + '::Step: Check the quiz block got configured');
      cy.get('.block_integrityadvocate').then(elt => {
        cy.wrap(elt).find('button[type=submit]').contains('Course Overview');
        expect(elt).to.not.contain('This block has no config');
        expect(elt).to.not.contain('No Api key is set');
        expect(elt).to.not.contain('No Application Id is set');
      });
    });
  });
});
