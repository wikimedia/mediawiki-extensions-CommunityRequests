'use strict';

const { mount } = require( '@vue/test-utils' );
const SpecialWishlistIntake = require( '../../../modules/intake/SpecialWishlistIntake.vue' );

const getWrapper = ( props = {} ) => {
	const form = document.createElement( 'form' );
	form.id = 'ext-communityrequests-intake-form';
	document.body.appendChild( form );
	return mount( SpecialWishlistIntake, {
		propsData: props,
		attachTo: form
	} );
};

const defaultProps = {
	audience: 'Test Audience',
	baseLang: 'en',
	baseRevId: 12345,
	created: '2023-10-01T12:00:00Z',
	description: 'Test Description',
	otherProject: 'Other Project',
	phabTasks: [ 'T123', 'T456' ],
	projects: [ 'commons', 'wikisource' ],
	proposer: 'MusikAnimal',
	status: 'submitted',
	title: 'Test Title',
	type: 'bug'
};

describe( 'SpecialWishlistIntake', () => {
	let wrapper;

	beforeEach( () => {
		mockMwConfigGet( { wgCanonicalSpecialPageName: 'WishlistIntake' } );
		document.body.innerHTML = '';
	} );

	afterEach( () => {
		wrapper.unmount();
	} );

	it( 'should have all the expected form fields', async () => {
		wrapper = getWrapper( defaultProps );
		const formData = new FormData(
			document.querySelector( '#ext-communityrequests-intake-form' )
		);
		// Status should be hidden for non-staff users
		expect( wrapper.find( '.ext-communityrequests-intake__status' ).exists() )
			.toBe( false );
		expect( formData.get( 'audience' ) ).toBe( 'Test Audience' );
		expect( formData.get( 'baserevid' ) ).toBe( '12345' );
		expect( formData.get( 'baselang' ) ).toBe( 'en' );
		expect( formData.get( 'created' ) ).toBe( '2023-10-01T12:00:00Z' );
		expect( formData.get( 'description' ) ).toBe( 'Test Description' );
		expect( formData.get( 'otherproject' ) ).toBe( 'Other Project' );
		expect( formData.get( 'phabtasks' ) ).toBe( 'T123,T456' );
		expect( formData.get( 'projects' ) ).toBe( 'commons,wikisource' );
		expect( formData.get( 'proposer' ) ).toBe( 'MusikAnimal' );
		expect( formData.get( 'status' ) ).toBe( 'submitted' );
		expect( formData.get( 'type' ) ).toBe( 'bug' );
		expect( formData.get( 'wishtitle' ) ).toBe( 'Test Title' );
	} );

	it( 'should show the status field for staff', () => {
		mockMwConfigGet( { intakeWishlistManager: true } );
		wrapper = getWrapper( defaultProps );
		expect( wrapper.find( '.ext-communityrequests-intake__status' ).exists() )
			.toBe( true );
	} );
} );
