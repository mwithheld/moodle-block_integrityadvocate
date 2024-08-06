// Cypress.env('xhr_logging_enabled', false);
Cypress.Commands.add('disable_xhr', () => {
    Cypress.env('xhr_logging_enabled', false);
})
Cypress.Commands.add('enable_xhr', () => {
    Cypress.env('xhr_logging_enabled', true);
})

Cypress.Commands.add('login', (username, password) => {
    const debug = false;
    const fxn = 'login';
    cy.log(fxn + '::Started');

    var returnThis = cy.session(
        username,
        () => {
            cy.visit(Cypress.env('login'));
            cy.get('#username').type(username);
            cy.get('#password').type(password);
            cy.get('#loginbtn').click();
            cy.get('nav').should('contain.text', 'Site administration');
        },
        {
            validate: () => {
                cy.getCookie('MoodleSession').should('exist')
            },
        });

    cy.log(fxn + '::Done');
    return returnThis;
})

Cypress.Commands.add('goto_course_home', (url, course_fullname) => {
    const debug = false;
    const fxn = 'goto_course_home';
    cy.log(fxn + '::Started');

    cy.visit(url);
    cy.get('body').should('have.class', 'path-course-view');
    var returnThis = cy.get('#page h1').should('contain.text', course_fullname);

    cy.log(fxn + '::Done');
    return returnThis;
})

Cypress.Commands.add('course_editing_on', () => {
    const debug = false;
    const fxn = 'course_editing_on';
    cy.log(fxn + '::Started');

    cy.get('input[name="setmode"]').check();
    var returnThis = cy.get('body').should('have.class', 'editing');

    cy.log(fxn + '::Done');
    return returnThis;
})

// /**
//  * If it is not already, open the nav drawer (left-hand menu).
//  *
//  * @returns {object} Whatever cy.get('body') returns =  the DOM element it found.
//  */
// Cypress.Commands.add('navdrawer_open', () => {
//     const debug = false;
//     debug && cy.log('navdrawer_open::Started');

//     // If the navdrawer is closed, open it.
//     return cy.get('body').then(body => {
//         if (body.find('div#nav-drawer.closed').length > 0) {
//             debug && cy.log('navdrawer_open::sidebar is closed');
//             cy.get('button[data-preference=drawer-open-nav').trigger('mouseover').click().then(() => {
//                 debug && cy.log('navdrawer_open::sidebar should now be opened');
//                 cy.get("div#nav-drawer").should('not.have.class', 'closed');
//             });
//         } else {
//             debug && cy.log('navdrawer_open::navdrawer is already open');
//         }
//     });
// })

/**
 * Add the IA block to the current page.
 * Assumes we are already on the target page, course editing mode is on.
 *
 * @returns {object} Whatever cy.get('#nav-drawer span') returns = the DOM element it found.
 */
Cypress.Commands.add('block_ia_add_to_page', () => {
    const debug = false;
    const fxn = 'block_ia_add_to_page';
    cy.log(fxn + '::Started');

    // cy.navdrawer_open();
    var returnThis = cy.get('div .add_block_button').click().then(() => {
        cy.get('.list-group-item-action', { timeout: 10000 }).should('contain', Cypress.env('block_fullname')).then((eltouter) => {
            cy.wrap(eltouter).contains(Cypress.env('block_fullname')).then(elt => {
                cy.wrap(elt).click().then(() => {
                    cy.get('body').then(body => {
                        cy.wrap(body).find('section.block_integrityadvocate').should('have.length', 1);
                    });
                });
            });
        });
    });

    cy.log(fxn + '::Done');
    return returnThis;
})

Cypress.Commands.add('remove_block_settings', () => {
    const debug = false;
    const fxn = 'remove_block_settings';
    cy.log(fxn + '::Started');

    var returnThis= cy.exec('export MOODLE_DOCKER_WWWROOT=$(pwd -LP)/../moodle; export MOODLE_DOCKER_DB=pgsql; cd ../moodle-docker; bin/moodle-docker-compose exec webserver bash -c "moosh sql-run \\"DELETE FROM m_block_instances WHERE blockname=\'integrityadvocate\'\\""', { failOnNonZeroExit: false }).then((result) => {
        cy.log(result.stderr);
        cy.log(result.stdout);
    });

    // cy.exec("cd ../moodle-docker; bin/moodle-docker-compose exec webserver bash -c 'webserver moosh plugin-uninstall availability_integrityadvocate'").then((result) => {
    //     cy.log(result.stderr);
    //     cy.log(result.stdout);
    // });
    // cy.exec("bin/moodle-docker-compose exec webserver bash -i -T webserver moosh plugin-uninstall availability_integrityadvocate")

    // cy.visit('/admin/plugins.php?uninstall=availability_integrityadvocate&confirm=0&return=overview');
    // cy.get('#notice button[type="submit"]').click();
    // cy.get('.continuebutton button[type="submit"]').click();

    // cy.visit('/admin/plugins.php?uninstall=block_integrityadvocate&confirm=0&return=overview');
    // cy.get('#notice button[type="submit"]').click();
    // cy.get('.continuebutton button[type="submit"]').click();
    // cy.get('.continuebutton button[type="submit"]').click();
    // cy.get('.continuebutton button[type="submit"]').click();
    // cy.course_editing_on();

    // // Add the IA block.
    // cy.get('body').then(body => {
    //     cy.wrap(body).find('section.block_integrityadvocate').should('have.length', 0);
    //         cy.block_ia_add_to_page();
    //         cy.log(fxn + '::DONE: Added block');
    // });

    cy.log(fxn + '::Done');
    return returnThis;
})