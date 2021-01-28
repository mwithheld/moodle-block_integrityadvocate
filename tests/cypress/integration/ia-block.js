// Refs:
// - Code re-use: https://stackoverflow.com/questions/59008563/cypress-re-use-auth-token-across-multiple-api-tests

require('cypress-xpath')

Cypress.config('baseUrl', 'http://127.0.0.1');
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

function _x(STR_XPATH) {
  var xresult = document.evaluate(STR_XPATH, document, null, XPathResult.ANY_TYPE, null);
  var xnodes = [];
  var xres;
  while (xres = xresult.iterateNext()) {
    xnodes.push(xres);
  }

  return xnodes;
}

Cypress.Commands.add('get_by_xpath', (xpath) => {
  return Cypress.$(_x(xpath));
});

Cypress.Commands.add('count_by_xpath', (xpath) => {
  return cy.get_by_xpath(xpath).length;
});

Cypress.Commands.add('login', (username, password) => {
  cy.request({
    method: 'POST',
    url: url.login,
    form: true, // Indicates the body should be form urlencoded and sets Content-Type: application/x-www-form-urlencoded headers.
    body: {
      username: username,
      password: password,
    },
  })
});

describe('ia-block', () => {
  before(() => {
    cy.login(strings.username_admin, strings.password_admin);
  });

  // Setup done this way is an anti-pattern, but can't be done properly if we are switching b/t Moodles on different servers.
  it('setup-reset', function () {
    cy.log('Step: Check if we should delete the old course');
    cy.visit(url.course_management + '?search=' + strings.coursename);
    //if (cy.count_by_xpath("//div[contains(.//*, '" + strings.coursename + "')]//ancestor::div[contains(@class, 'course-listing')]") > 0) {
    //if(cy.get('.course-listing .listitem-course').contains(strings.coursename))
    // This only works if there's 100% guarantee body has fully rendered without any pending changes to its state.
    //cy.get('.course-listing .listitem-course').then(($elt) => {
    if (Cypress.$(".course-listing .listitem-course:contains('" + strings.coursename + "')")) {
      //cy.log('The search found some courses');
      // Synchronously ask for the body's text and do something based on whether it includes another string.
      //   // if ($elt.text().includes(strings.coursename)) {
      cy.log('About to delete the test course');
      // Ask to delete.
      cy.get(" .listitem-course:contains('" + strings.coursename + "')").find('a.action-delete i').click();
      // Confirm delete.
      cy.get("#modal-footer button.btn-primary").click();
      //   // }
    }

    cy.log('Step: Create a new empty course to hold the test course content');
    cy.visit(url.course_management);
    cy.get('.course-listing-actions > .btn-default').click();
    cy.get('#id_fullname').type(strings.coursename);
    cy.get('#id_shortname').type(strings.coursename);
    cy.get('#id_enddate_enabled').uncheck();
    cy.get('#id_saveanddisplay').click();

    cy.get('.breadcrumb').should('contain', strings.coursename);
  });

  // it('goto-course-home', function() {
  //   cy.visit(url.course_home);
  // });
});

