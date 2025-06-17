'use strict';

const UserPreferences = require( '../userpreferences.js' );
const IntakePage = require( '../pageobjects/wishlistintake.page.js' );
const ViewWishPage = require( '../pageobjects/viewwish.page.js' );
const LoginPage = require( 'wdio-mediawiki/LoginPage.js' );
const Api = require( 'wdio-mediawiki/Api.js' );
const { config } = require( '../../../extension.json' );

describe( 'WishlistIntake wish submission', () => {

	it( 'should prompt logged out users to login', async () => {
		await IntakePage.open();
		await browser.waitUntil( () => LoginPage.loginButton.isDisplayed(), { timeout: 5000 } );
	} );

	it( 'should show the form with VisualEditor when browsing to the intake form', async () => {
		await LoginPage.loginAdmin();
		await UserPreferences.enableVisualEditor();
		await IntakePage.open();
		await expect( IntakePage.titleInput ).toBeDisplayed();
		await expect( IntakePage.descriptionInput ).toBeDisplayed();
	} );

	it( 'should show errors when submitting an incomplete form', async () => {
		await IntakePage.submitButton.click();

		await expect( IntakePage.titleError ).toBeDisplayed();
		await expect( IntakePage.descriptionError ).toBeDisplayed();
		await expect( IntakePage.typeError ).toBeDisplayed();
		await expect( IntakePage.projectsError ).toBeDisplayed();
		await expect( IntakePage.audienceError ).toBeDisplayed();
	} );

	it( 'should not show an error if a title is over 100 chars because of translate tags', async () => {
		await IntakePage.titleInput.setValue( 'Lorem ipsum this is 103 characters long and test test test test test test test test test test test test' );
		await IntakePage.submitButton.click();
		await expect( IntakePage.titleError ).toBeDisplayed();
		await IntakePage.titleInput.setValue( 'Lorem ipsum this is 97 characters long and test test test test test test test test test test test' );
		await IntakePage.submitButton.click();
		await expect( IntakePage.titleError ).not.toBeDisplayed();
		await IntakePage.titleInput.setValue( '<translate><!--T:1--> Lorem ipsum this is 97 characters long and test test test test test test test test test test test</translate>' );
		await IntakePage.submitButton.click();
		await expect( IntakePage.titleError ).not.toBeDisplayed();
	} );

	it( 'should reveal all projects and the "It\'s something else" field when the "All projects" checkbox is checked', async () => {
		await IntakePage.allProjectsCheckbox.click();
		await expect( IntakePage.otherProjectInput ).toBeDisplayed();
	} );

	it( 'should hide errors if all required fields are filled in on submission', async () => {
		await IntakePage.titleInput.setValue( 'This is a test wish' );
		await IntakePage.descriptionInput.click();
		await IntakePage.descriptionInput.setValue( 'This is a test description.\n'.repeat( 10 ) );
		await IntakePage.firstWishTypeInput.click();
		await IntakePage.audienceInput.setValue( 'This is a test audience' );
		await IntakePage.phabricatorTasksInput.setValue( 'T123,T456' );
		await IntakePage.submitButton.waitForClickable();
		await IntakePage.submitButton.click();
		await expect( IntakePage.titleError ).not.toBeDisplayed();
		await expect( IntakePage.descriptionError ).not.toBeDisplayed();
		await expect( IntakePage.typeError ).not.toBeDisplayed();
		await expect( IntakePage.projectsError ).not.toBeDisplayed();
		await expect( IntakePage.audienceError ).not.toBeDisplayed();
	} );

	it( 'should show all the data entered in the form', async () => {
		await expect( ViewWishPage.successMessage ).toBeDisplayed( { timeout: 30 } );
		const pageTitle = await browser.execute( () => mw.config.get( 'wgTitle' ) );
		await expect( pageTitle ).toMatch(
			// eslint-disable-next-line security/detect-non-literal-regexp
			new RegExp( `^${ config.CommunityRequestsWishPagePrefix.value }\\d+$` )
		);

		await expect( ( await ViewWishPage.wishTitle.getText() ).trim() ).toBe( 'This is a test wish' );
		await expect( await ViewWishPage.statusChip.getText() ).toBe( 'Submitted' );
		await expect( await ViewWishPage.description.getText() ).toContain(
			'This is a test description.'
		);
		await expect( await ViewWishPage.wishType.getText() ).toBe( 'Feature request' );
		await expect( await ViewWishPage.projects.getText() ).toBe( 'All projects' );
		await expect( ( await ViewWishPage.audience.getText() ).trim() ).toBe(
			'This is a test audience'
		);
		await expect( await ViewWishPage.phabTasks.getText() ).toBe( 'T123, T456' );
		await expect( await ViewWishPage.proposer.getText() ).toBe(
			`Author: ${ browser.config.mwUser } (talk)`
		);

		const bot = await Api.bot();
		await bot.delete( pageTitle, 'Test cleanup' ).catch( ( e ) => {
			console.error( e );
		} );
	} );
} );
