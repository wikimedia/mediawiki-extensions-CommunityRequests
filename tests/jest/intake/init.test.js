'use strict';

describe( 'Intake init', () => {

	beforeEach( () => {
		const bodyContent = document.createElement( 'div' );
		bodyContent.className = 'mw-body-content';
		document.body.appendChild( bodyContent );
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		jest.resetModules();
	} );

	it( 'should show a post-edit success message after creating a new wish', () => {
		mockMwConfigGet( {
			wgAction: 'view',
			wgPageName: 'Community Wishlist/W1',
			intakePostEdit: 'created'
		} );
		jest.requireActual( '../../../modules/intake/init.js' );
		expect( document.querySelector( '.cdx-message' ).textContent )
			.toBe( 'communityrequests-create-success communityrequests-view-all-wishes' );
	} );

	it( 'should show a post-edit success message after editing a new wish', () => {
		mockMwConfigGet( {
			wgAction: 'view',
			wgPageName: 'Community Wishlist/W1',
			intakePostEdit: 'updated'
		} );
		jest.requireActual( '../../../modules/intake/init.js' );
		expect( document.querySelector( '.cdx-message' ).textContent )
			.toBe( 'communityrequests-edit-success communityrequests-view-all-wishes' );
	} );

	it( 'should show a post-edit success message after creating a new focus area', () => {
		mockMwConfigGet( {
			wgAction: 'view',
			wgPageName: 'Community Wishlist/FA1',
			intakePostEdit: 'created'
		} );
		jest.requireActual( '../../../modules/intake/init.js' );
		expect( document.querySelector( '.cdx-message' ).textContent )
			.toBe( 'communityrequests-focus-area-create-success communityrequests-view-all-focus-areas' );
	} );

	it( 'should show a post-edit success message after editing a focus area', () => {
		mockMwConfigGet( {
			wgAction: 'view',
			wgPageName: 'Community Wishlist/FA1',
			intakePostEdit: 'updated'
		} );
		jest.requireActual( '../../../modules/intake/init.js' );
		expect( document.querySelector( '.cdx-message' ).textContent )
			.toBe( 'communityrequests-focus-area-edit-success communityrequests-view-all-focus-areas' );
	} );
} );
