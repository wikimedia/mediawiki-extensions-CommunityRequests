'use strict';

const { mount } = require( '@vue/test-utils' );
const SpecialEditFocusArea = require( '../../../modules/intake/SpecialEditFocusArea.vue' );

const getWrapper = ( props = {} ) => {
	const form = document.createElement( 'form' );
	form.id = 'ext-communityrequests-intake-form';
	document.body.appendChild( form );
	return mount( SpecialEditFocusArea, {
		propsData: props,
		attachTo: form
	} );
};

const defaultProps = {
	baselang: 'en',
	baserevid: 12345,
	created: '2023-10-01T12:00:00Z',
	description: 'Test Description',
	shortdescription: 'Test Short Description',
	owners: '* Community Tech\n* Editing',
	volunteers: '* User1\n* User2',
	status: 'draft',
	title: 'Test Title'
};

describe( 'SpecialEditFocusArea', () => {
	let wrapper;

	beforeEach( () => {
		mockMwConfigGet( { wgCanonicalSpecialPageName: 'EditFocusArea' } );
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
		expect( formData.get( 'baserevid' ) ).toBe( '12345' );
		expect( formData.get( 'created' ) ).toBe( '2023-10-01T12:00:00Z' );
		expect( formData.get( 'description' ) ).toBe( 'Test Description' );
		expect( formData.get( 'shortdescription' ) ).toBe( 'Test Short Description' );
		expect( formData.get( 'owners' ) ).toBe( '* Community Tech\n* Editing' );
		expect( formData.get( 'volunteers' ) ).toBe( '* User1\n* User2' );
		expect( formData.get( 'status' ) ).toBe( 'draft' );
		expect( formData.get( 'entitytitle' ) ).toBe( 'Test Title' );
		expect( formData.get( 'baselang' ) ).toBe( 'en' );
	} );
} );
