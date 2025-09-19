'use strict';

describe( 'Voting init', () => {

	beforeAll( () => {
		jest.mock( 'vue', jest.fn );
	} );

	beforeEach( () => {
		const bodyContent = document.createElement( 'div' );
		bodyContent.className = 'mw-body-content';
		const votingContainer = document.createElement( 'div' );
		votingContainer.className = 'ext-communityrequests-voting';
		bodyContent.appendChild( votingContainer );
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
			crPostEdit: 'entity-created'
		} );
		jest.requireActual( '../../../modules/voting/init.js' );
		expect( document.querySelector( '.cdx-message' ).textContent )
			.toBe( 'communityrequests-wish-create-success communityrequests-view-all-wishes' );
	} );

	it( 'should show a post-edit success message after editing a new wish', () => {
		mockMwConfigGet( {
			wgAction: 'view',
			wgPageName: 'Community Wishlist/W1',
			crPostEdit: 'entity-updated'
		} );
		jest.requireActual( '../../../modules/voting/init.js' );
		expect( document.querySelector( '.cdx-message' ).textContent )
			.toBe( 'communityrequests-wish-edit-success communityrequests-view-all-wishes' );
	} );

	it( 'should show a post-edit success message after creating a new focus area', () => {
		mockMwConfigGet( {
			wgAction: 'view',
			wgPageName: 'Community Wishlist/FA1',
			crPostEdit: 'entity-created'
		} );
		jest.requireActual( '../../../modules/voting/init.js' );
		expect( document.querySelector( '.cdx-message' ).textContent )
			.toBe( 'communityrequests-focus-area-create-success communityrequests-view-all-focus-areas' );
	} );

	it( 'should show a post-edit success message after editing a focus area', () => {
		mockMwConfigGet( {
			wgAction: 'view',
			wgPageName: 'Community Wishlist/FA1',
			crPostEdit: 'entity-updated'
		} );
		jest.requireActual( '../../../modules/voting/init.js' );
		expect( document.querySelector( '.cdx-message' ).textContent )
			.toBe( 'communityrequests-focus-area-edit-success communityrequests-view-all-focus-areas' );
	} );

	it( 'should show a post-edit success message after voting on a wish', () => {
		mockMwConfigGet( {
			wgAction: 'view',
			wgPageName: 'Community Wishlist/W1',
			crPostEdit: 'vote-added'
		} );
		jest.requireActual( '../../../modules/voting/init.js' );
		expect( document.querySelector( '.cdx-message' ).textContent )
			.toBe( 'communityrequests-support-wish-confirmed communityrequests-view-all-wishes' );
	} );

	it( 'should show a post-edit success message after updating a vote', () => {
		mockMwConfigGet( {
			wgAction: 'view',
			wgPageName: 'Community Wishlist/W1',
			crPostEdit: 'vote-updated'
		} );
		jest.requireActual( '../../../modules/voting/init.js' );
		expect( document.querySelector( '.cdx-message' ).textContent )
			.toBe( 'communityrequests-support-updated communityrequests-view-all-wishes' );
	} );

	it( 'should show a post-edit success message after removing a vote', () => {
		mockMwConfigGet( {
			wgAction: 'view',
			wgPageName: 'Community Wishlist/W1',
			crPostEdit: 'vote-removed'
		} );
		jest.requireActual( '../../../modules/voting/init.js' );
		expect( document.querySelector( '.cdx-message' ).textContent )
			.toBe( 'communityrequests-support-removed communityrequests-view-all-wishes' );
	} );
} );
