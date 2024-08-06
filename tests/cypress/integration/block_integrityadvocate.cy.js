//-----------------------------------------------------------------------------
//#region Global constants and vars
//-----------------------------------------------------------------------------
// Base URL with no trailing slash.
const strings = {
    username_admin: 'admin',
    password_admin: 'test',
    appid: '',
    apikey: '',

    baseurl: Cypress.config().baseUrl,
    // coursename: 'ia-automated-tests',
    course_name: 'testcourse01',
    course_fullname: 'Test course 01',
    block_fullname: 'Integrity Advocate',
    block_shortname: 'block_integrityadvocate',
}
const urls = {
    baseurl: strings.baseurl,
    home: '/',
    login: '/login/',

    course_home: '/course/view.php?name=' + strings.course_name,
    course_management: '/course/management.php',
    course_search: '/course/management.php?search=',
}
//#endregion

Cypress.env('xhr_logging_enabled', false);

beforeEach(() => {
    cy.intercept({ resourceType: /xhr|fetch/ }, { log: Cypress.env('xhr_logging_enabled') });
})

/**
 * Add the IA block to the current page.
 * Assumes we are already on the target page, course editing mode is on.
 *
 * @returns {object} Whatever cy.get('#nav-drawer span') returns = the DOM element it found.
 */
const block_ia_add_to_page = () => {
    const debug = false;
    const fxn = 'block_ia_add_to_page';
    cy.log(fxn + '::Started');

    cy.log(fxn + '::Done');
    return returnThis;
};

/**
 * On the current page, configure the IA block with the params passed in, then assert it worked.
 * 
 * @param {string} appid 
 * @param {string} apikey 
 * @returns {object} Whatever cy.get('.block_integrityadvocate') returns = the DOM element it found.
 */
const block_ia_configure = (appid = '', apikey = '', pagetypepattern = '') => {
    const debug = false;
    const fxn = 'block_ia_configure';
    cy.log(fxn + '::Started with appid=' + appid + '; apikey=' + apikey + '; pagetypepattern=' + pagetypepattern);

    var returnThis = cy.get('.block_integrityadvocate').as('block_integrityadvocate').find('.action-menu .dropdown-toggle.icon-no-margin').click().then(() => {
        cy.get('@block_integrityadvocate').find('.dropdown-menu .editing_edit').click({ force: true }).then(() => {
            cy.get('body').should('contain', 'Block settings');

            appid && cy.get('#id_config_appid').type(appid);
            apikey && cy.get('#id_config_apikey').type(apikey);

            if (pagetypepattern) {
                cy.get('.ftoggler a[aria-controls="id_whereheader"]').trigger('mouseover').click({ force: true }).then(() => {
                    cy.get('#id_bui_pagetypepattern').scrollIntoView().select(pagetypepattern);
                });
            }

            cy.get('#id_submitbutton').click().then(() => {
                cy.get('@block_integrityadvocate').then(elt => {
                    cy.location('href').then(url => {
                        block_ia_assert_instructor_course_view({ elt: elt, configured: true, is_module_level: !block_ia_is_url_course_home(url) });
                    });
                });
            });
        });
    });

    cy.log(fxn + '::Done');
    return returnThis;
};

describe('add_block_new_course', () => {
    it('passes', {
        env: {
            ...strings,
            ...urls,
        },
    },
        () => {
            const debug = false;
            const fxn = 'add_block_new_course';
            cy.log(fxn + '::Started');

            cy.remove_block_settings();

            cy.login(Cypress.env('username_admin'), Cypress.env('password_admin'));

            cy.goto_course_home(Cypress.env('course_home'), Cypress.env('course_fullname'));
            cy.course_editing_on();

            // Add the IA block.
            cy.get('body').then(body => {
                cy.wrap(body).find('section.block_integrityadvocate').should('have.length', 0);
                    cy.block_ia_add_to_page();
                    cy.log(fxn + '::DONE: Added block');
            });

            // Assertions.
            cy.get('.block_integrityadvocate').as('block_integrityadvocate')
                .should('be.visible')

                .should('contain.text', 'This block is missing config')
                .should('contain.text', 'No API key is set')
                .should('contain.text', 'No Application Id is set')

                .find('.block_integrityadvocate_plugininfo[title="Version"]').should('be.visible')
                ;
            cy.get('@block_integrityadvocate')
                .find('.block_integrityadvocate_plugininfo[title="Block Id"]').should('be.visible')
                ;

            block_ia_configure(Cypress.env('appid'), Cypress.env('apikey'));

            cy.log(fxn + '::Done');
        });
})

