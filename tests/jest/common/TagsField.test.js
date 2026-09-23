'use strict';

const { mount } = require( '@vue/test-utils' );
const { CdxMultiselectLookup } = require( '@wikimedia/codex' );
const TagsField = require( '../../../modules/common/TagsField.vue' );

const availableTags = [
	{ value: 'multimedia', label: 'Multimedia' },
	{ value: 'wikisource', label: 'Wikisource' },
	{ value: 'translation', label: 'Translation' }
];

const getWrapper = ( props = {} ) => mount( TagsField, {
	props: Object.assign( {
		tags: [ 'multimedia' ],
		availableTags,
		labelMessage: 'communityrequests-tags-label'
	}, props )
} );

describe( 'TagsField', () => {
	let wrapper;

	afterEach( () => {
		if ( wrapper ) {
			wrapper.unmount();
			wrapper = null;
		}
		jest.restoreAllMocks();
	} );

	it( 'should initialize the selected tags and chips', () => {
		wrapper = getWrapper();
		const lookup = wrapper.findComponent( CdxMultiselectLookup );

		expect( lookup.props( 'selected' ) ).toEqual( [ 'multimedia' ] );
		expect( lookup.props( 'inputChips' ) ).toEqual( [ availableTags[ 0 ] ] );
		expect( lookup.props( 'menuItems' ) ).toEqual( availableTags );
	} );

	it( 'should emit the selected tag identifiers as an array', async () => {
		wrapper = getWrapper();
		const lookup = wrapper.findComponent( CdxMultiselectLookup );

		lookup.vm.$emit( 'update:selected', [ 'wikisource', 'translation' ] );
		await wrapper.vm.$nextTick();

		expect( wrapper.emitted( 'update:tags' ) ).toEqual( [
			[ [ 'wikisource', 'translation' ] ]
		] );
	} );

	it( 'should filter and restore the available tags', async () => {
		wrapper = getWrapper();
		const lookup = wrapper.findComponent( CdxMultiselectLookup );

		lookup.vm.$emit( 'update:input-value', 'source' );
		lookup.vm.$emit( 'input', 'source' );
		await wrapper.vm.$nextTick();
		expect( lookup.props( 'menuItems' ) ).toEqual( [ availableTags[ 1 ] ] );

		lookup.vm.$emit( 'update:input-value', '' );
		lookup.vm.$emit( 'input', '' );
		await wrapper.vm.$nextTick();
		expect( lookup.props( 'menuItems' ) ).toEqual( availableTags );
	} );

	it( 'should not show or build a description by default', () => {
		const messageSpy = jest.spyOn( mw, 'message' );

		wrapper = getWrapper();

		expect( wrapper.find( '.cdx-label__description' ).exists() ).toBe( false );
		expect( messageSpy ).not.toHaveBeenCalled();
	} );

	it( 'should show the description with its params', () => {
		const messageSpy = jest.spyOn( mw, 'message' )
			.mockImplementation( () => ( {
				parse: () => 'See the <a href="/wiki/FAQ">FAQ</a>.'
			} ) );

		wrapper = getWrapper( {
			showDescription: true,
			descriptionMessage: 'communityrequests-affected-wikis-description',
			descriptionParams: [ 'Community Wishlist/FAQ#definition' ]
		} );

		expect( messageSpy ).toHaveBeenCalledWith(
			'communityrequests-affected-wikis-description',
			'Community Wishlist/FAQ#definition'
		);
		const link = wrapper.find( '.cdx-label__description a' );
		expect( link.exists() ).toBe( true );
		expect( link.attributes( 'href' ) ).toBe( '/wiki/FAQ' );
		expect( link.text() ).toBe( 'FAQ' );
	} );
} );
