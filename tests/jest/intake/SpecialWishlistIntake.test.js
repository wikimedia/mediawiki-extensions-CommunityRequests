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
	title: 'Test Title',
	description: 'Test Description',
	type: 'bug',
	projects: [ 'commons', 'wikisource' ],
	otherProject: 'Other Project',
	audience: 'Test Audience',
	phabTasks: [ 'T123', 'T456' ],
	status: 'submitted'
};

describe( 'SpecialWishlistIntake', () => {
	let wrapper;

	beforeEach( () => {
		mockMwConfigGet( { intakeVeModules: [] } );
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
		expect( formData.get( 'wishtitle' ) ).toBe( 'Test Title' );
		expect( formData.get( 'description' ) ).toBe( 'Test Description' );
		expect( formData.get( 'type' ) ).toBe( 'bug' );
		expect( formData.get( 'projects' ) ).toBe( 'commons,wikisource' );
		expect( formData.get( 'otherProject' ) ).toBe( 'Other Project' );
		expect( formData.get( 'audience' ) ).toBe( 'Test Audience' );
		expect( formData.get( 'phabTasks' ) ).toBe( 'T123,T456' );
		// Only shown for staff
		expect( formData.get( 'status' ) ).toBeNull();
	} );

	it( 'should show the status field for staff', () => {
		mockMwConfigGet( { wgUserName: 'ExampleUser-WMF' } );
		wrapper = getWrapper( defaultProps );
		const formData = new FormData(
			document.querySelector( '#ext-communityrequests-intake-form' )
		);
		expect( formData.get( 'status' ) ).toBe( 'submitted' );
	} );
} );
