'use strict';
const UserPreferences = require( '../userpreferences.js' );
const IntakePage = require( '../pageobjects/wishlistintake.page.js' );
const ViewWishPage = require( '../pageobjects/viewwish.page.js' );
const LoginPage = require( 'wdio-mediawiki/LoginPage.js' );
const Util = require( 'wdio-mediawiki/Util.js' );
const Api = require( 'wdio-mediawiki/Api.js' );
const wgConfig = {
	CommunityRequestsWishPagePrefix: 'Community Wishlist/Wishes/'
};

describe( 'WishlistIntake wish submission', () => {
	let title;

	before( () => {
		title = Util.getTestString( 'Selenium test wish' );
	} );

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
		await IntakePage.titleInput.setValue( title );
		await IntakePage.descriptionInput.click();
		await IntakePage.descriptionInput.setValue( 'This is a test description.\n'.repeat( 10 ) );
		await IntakePage.firstWishTypeInput.click();
		await IntakePage.audienceInput.setValue( 'This is a test audience' );
		await IntakePage.phabricatorTasksInput.setValue( 'T123,T456' );
		await IntakePage.submitButton.waitForClickable();
		await IntakePage.submitButton.click();
		await expect( IntakePage.titleError ).not.toBeDisplayed();
	} );

	// FIXME: restore test after <community-request> parser tag is implemented.
	it.skip( 'should show all the data entered in the form', async () => {
		await expect( ViewWishPage.successMessage ).toBeDisplayed( { timeout: 8000 } );
		await expect(
			await browser.execute( () => mw.config.get( 'wgTitle' ) )
		).toBe( wgConfig.CommunityRequestsWishPagePrefix + title );

		await expect( ( await ViewWishPage.wishTitle.getText() ).trim() ).toBe( title );
		await expect( await ViewWishPage.statusChip.getText() ).toBe( 'Submitted' );
		await expect( await ViewWishPage.description.getText() ).toContain(
			'This is a test description.'
		);
		await expect( await ViewWishPage.wishType.getText() ).toBe( 'Feature request' );
		await expect( await ViewWishPage.projects.getText() ).toBe( 'All projects' );
		await expect( ( await ViewWishPage.audience.getText() ).trim() ).toBe(
			'This is a test audience'
		);
	} );

	after( async () => {
		const bot = await Api.bot();
		bot.delete( wgConfig.CommunityRequestsWishPagePrefix + title, 'Test cleanup' ).catch( ( e ) => {
			console.error( e );
		} );
	} );
} );
