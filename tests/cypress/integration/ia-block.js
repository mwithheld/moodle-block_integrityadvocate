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
  coursename: 'ia-automated-tests'
}
var url = {
  home: '/',
  login: '/login/',

  course_home: '/course/view.php?name=' + strings.coursename,
  course_management: '/course/management.php'
}

// function _x(STR_XPATH) {
//   var xresult = document.evaluate(STR_XPATH, document, null, XPathResult.ANY_TYPE, null);
//   var xnodes = [];
//   var xres;
//   while (xres = xresult.iterateNext()) {
//     xnodes.push(xres);
//   }

//   return xnodes;
// }

// Cypress.Commands.add('get_by_xpath', (xpath) => {
//   return Cypress.$(_x(xpath));
// });

// Cypress.Commands.add('count_by_xpath', (xpath) => {
//   return cy.get_by_xpath(xpath).length;
// });

Cypress.Commands.add('login', (url, username, password) => {
  cy.log("login:  Started with url.login=", url);
  cy.request('/login')
    .its('body')
    .then((body) => {
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
      }).then((resp) => {
        expect(resp.status).to.eq(200);
        expect(resp.body).to.include('You are logged in as');
        cy.url().should('not.include', '/login');
      });
    });
});

describe('ia-block', () => {
  before(() => {
    cy.login(Cypress.config().baseUrl + url.login, strings.username_admin, strings.password_admin);
    cy.log('before(): Done');
  });

  // Setup done this way is an anti-pattern, but can't be done properly if we are switching b/t Moodles on different servers.
  it('setup-reset', function () {
    cy.log('Step: Check if we should delete the old course');
    cy.visit(url.course_management + '?search=' + strings.coursename).then((contentWindow) => {
      let count = Cypress.$(".course-listing .listitem-course:contains('" + strings.coursename + "')").length;
      cy.log('coursename matches=' + count);
      if (count > 0) {
        cy.log('About to delete the test course');
        // Hit the delete trash can link.
        cy.get(" .listitem-course:contains('" + strings.coursename + "')").find('a.action-delete i').click();
        // Confirm delete.
        cy.get("#modal-footer button.btn-primary").click();
      }
    });

    cy.log('Step: Create a new empty course to hold the test course content');
    cy.visit(url.course_management).then((contentWindow) => {
      cy.get('.course-listing-actions > a.btn').click();
      cy.get('#id_fullname').type(strings.coursename);
      cy.get('#id_shortname').type(strings.coursename);
      cy.get('#id_enddate_enabled').uncheck();
      cy.get('#id_saveanddisplay').click();
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
  });

  // it('goto-course-home', function() {
  //   cy.visit(url.course_home);
  // });
});

