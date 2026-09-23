'use strict';

const { flushPromises, mount } = require( '@vue/test-utils' );
const SpecialWishlistIntake = require( '../../../modules/intake/SpecialWishlistIntake.vue' );
const TagsField = require( '../../../modules/common/TagsField.vue' );

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
	baselang: 'en',
	baserevid: 12345,
	created: '2023-10-01T12:00:00Z',
	description: 'Test Description',
	focusarea: 'FA1',
	phabtasks: [ 'T123', 'T456' ],
	tags: [ 'large-wikipedias', 'multimedia' ],
	availableTags: [
		{ value: 'large-wikipedias', label: 'Large Wikipedias', type: 'affected-wikis' },
		{ value: 'other-wikipedias', label: 'Other Wikipedias', type: 'affected-wikis' },
		{ value: 'sister-projects', label: 'Sister projects', type: 'affected-wikis' },
		{ value: 'multimedia', label: 'Multimedia', type: 'standard' },
		{ value: 'wikisource', label: 'Wikisource', type: 'standard' }
	],
	proposer: 'MusikAnimal',
	status: 'under-review',
	title: 'Test Title',
	type: 'bug'
};

describe( 'SpecialWishlistIntake', () => {
	let wrapper;

	beforeEach( () => {
		mockMwConfigGet( {
			wgCanonicalSpecialPageName: 'WishlistIntake',
			intakeId: null,
			intakeFocusAreas: {
				FA1: 'Focus Area 1',
				FA2: 'Focus Area 2'
			}
		} );
		document.body.innerHTML = '';
	} );

	afterEach( () => {
		if ( wrapper ) {
			wrapper.unmount();
			wrapper = null;
		}
		jest.restoreAllMocks();
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
		expect( formData.get( 'focusarea' ) ).toBe( 'FA1' );
		expect( formData.get( 'phabtasks' ) ).toBe( 'T123,T456' );
		expect( formData.get( 'tags' ) ).toBe( 'large-wikipedias,multimedia' );
		expect( formData.get( 'proposer' ) ).toBe( 'MusikAnimal' );
		expect( formData.get( 'status' ) ).toBe( 'under-review' );
		expect( formData.get( 'type' ) ).toBe( 'bug' );
		expect( formData.get( 'entitytitle' ) ).toBe( 'Test Title' );
	} );

	it( 'should submit the under-review status when no status is supplied', () => {
		const props = { ...defaultProps };
		delete props.status;
		wrapper = getWrapper( props );
		const formData = new FormData(
			document.querySelector( '#ext-communityrequests-intake-form' )
		);
		expect( formData.get( 'status' ) ).toBe( 'under-review' );
	} );

	it( 'should restore affected-wikis and standard tags in their respective fields', () => {
		wrapper = getWrapper( defaultProps );
		const tagFields = wrapper.findAllComponents( TagsField );

		expect( tagFields[ 0 ].props( 'tags' ) ).toEqual( [ 'large-wikipedias' ] );
		expect( tagFields[ 0 ].props( 'availableTags' ) ).toEqual( [
			{ value: 'large-wikipedias', label: 'Large Wikipedias', type: 'affected-wikis' },
			{ value: 'other-wikipedias', label: 'Other Wikipedias', type: 'affected-wikis' },
			{ value: 'sister-projects', label: 'Sister projects', type: 'affected-wikis' }
		] );
		expect( tagFields[ 1 ].props( 'tags' ) ).toEqual( [ 'multimedia' ] );
		expect( tagFields[ 1 ].props( 'availableTags' ) ).toEqual( [
			{ value: 'multimedia', label: 'Multimedia', type: 'standard' },
			{ value: 'wikisource', label: 'Wikisource', type: 'standard' }
		] );
	} );

	it( 'should update tags when the affected-wikis field changes', async () => {
		wrapper = getWrapper( defaultProps );
		const tagFields = wrapper.findAllComponents( TagsField );

		tagFields[ 0 ].vm.$emit( 'update:tags', [ 'large-wikipedias', 'sister-projects' ] );
		await flushPromises();

		const formData = new FormData(
			document.querySelector( '#ext-communityrequests-intake-form' )
		);
		expect( formData.get( 'tags' ) ).toBe( 'multimedia,large-wikipedias,sister-projects' );
		expect( tagFields[ 0 ].props( 'tags' ) ).toEqual( [ 'large-wikipedias', 'sister-projects' ] );
		expect( tagFields[ 1 ].props( 'tags' ) ).toEqual( [ 'multimedia' ] );
	} );

	it( 'should update tags when the standard tags field changes', async () => {
		wrapper = getWrapper( defaultProps );
		const tagFields = wrapper.findAllComponents( TagsField );

		tagFields[ 1 ].vm.$emit( 'update:tags', [ 'multimedia', 'wikisource' ] );
		await flushPromises();

		const formData = new FormData(
			document.querySelector( '#ext-communityrequests-intake-form' )
		);
		expect( formData.get( 'tags' ) ).toBe( 'large-wikipedias,multimedia,wikisource' );
		expect( tagFields[ 0 ].props( 'tags' ) ).toEqual( [ 'large-wikipedias' ] );
		expect( tagFields[ 1 ].props( 'tags' ) ).toEqual( [ 'multimedia', 'wikisource' ] );
	} );

	it( 'should remove tags when unselected in the affected-wikis field', async () => {
		wrapper = getWrapper( defaultProps );
		const tagFields = wrapper.findAllComponents( TagsField );

		tagFields[ 0 ].vm.$emit( 'update:tags', [] );
		await flushPromises();

		const formData = new FormData(
			document.querySelector( '#ext-communityrequests-intake-form' )
		);
		expect( formData.get( 'tags' ) ).toBe( 'multimedia' );
		expect( tagFields[ 0 ].props( 'tags' ) ).toEqual( [] );
		expect( tagFields[ 1 ].props( 'tags' ) ).toEqual( [ 'multimedia' ] );
	} );

	it( 'should remove tags when unselected in the standard tags field', async () => {
		wrapper = getWrapper( defaultProps );
		const tagFields = wrapper.findAllComponents( TagsField );

		tagFields[ 1 ].vm.$emit( 'update:tags', [] );
		await flushPromises();

		const formData = new FormData(
			document.querySelector( '#ext-communityrequests-intake-form' )
		);
		expect( formData.get( 'tags' ) ).toBe( 'large-wikipedias' );
		expect( tagFields[ 0 ].props( 'tags' ) ).toEqual( [ 'large-wikipedias' ] );
		expect( tagFields[ 1 ].props( 'tags' ) ).toEqual( [] );
	} );

	it( 'should require an affected-wikis tag', async () => {
		const submitSpy = jest.spyOn( HTMLFormElement.prototype, 'submit' )
			.mockImplementation( () => {} );

		wrapper = getWrapper( Object.assign( {}, defaultProps, {
			description: 'This is a long description that meets the length requirements.',
			tags: [ 'multimedia' ]
		} ) );
		wrapper.vm.handleSubmit();
		await flushPromises();

		const affectedWikisField = wrapper.findAllComponents( TagsField )[ 0 ];
		expect( affectedWikisField.props( 'error' ) ).toBe( 'communityrequests-affected-wikis-error' );
		expect( affectedWikisField.find( '.cdx-message--error' ).text() )
			.toBe( 'communityrequests-affected-wikis-error' );
		expect( submitSpy ).not.toHaveBeenCalled();
	} );

	it( 'should not require an affected-wikis tag when none are available', async () => {
		const submitSpy = jest.spyOn( HTMLFormElement.prototype, 'submit' )
			.mockImplementation( () => {} );

		wrapper = getWrapper( Object.assign( {}, defaultProps, {
			description: 'This is a long description that meets the length requirements.',
			tags: [ 'multimedia' ],
			availableTags: defaultProps.availableTags.filter(
				( tag ) => tag.type !== 'affected-wikis'
			)
		} ) );
		wrapper.vm.handleSubmit();
		await flushPromises();

		expect( wrapper.findAllComponents( TagsField ) ).toHaveLength( 1 );
		expect( submitSpy ).toHaveBeenCalled();
	} );

	it( 'should not require an affected-wikis tag when editing an existing wish', async () => {
		const submitSpy = jest.spyOn( HTMLFormElement.prototype, 'submit' )
			.mockImplementation( () => {} );

		mockMwConfigGet( { intakeId: 123 } );

		wrapper = getWrapper( Object.assign( {}, defaultProps, {
			description: 'This is a long description that meets the length requirements.',
			tags: [ 'multimedia' ]
		} ) );
		wrapper.vm.handleSubmit();
		await flushPromises();

		const affectedWikisField = wrapper.findAllComponents( TagsField )[ 0 ];
		expect( affectedWikisField.props( 'error' ) ).toBe( '' );
		expect( submitSpy ).toHaveBeenCalled();
	} );

	it( 'should show the status and focus area fields for staff', () => {
		mockMwConfigGet( { intakeWishlistManager: true } );
		wrapper = getWrapper( defaultProps );
		expect( wrapper.find( '.ext-communityrequests-intake__status' ).exists() )
			.toBe( true );
		expect( wrapper.find( '.ext-communityrequests-intake__focus-area' ).exists() )
			.toBe( true );
	} );
} );
