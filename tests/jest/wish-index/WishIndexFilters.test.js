'use strict';

const { mount } = require( '@vue/test-utils' );
const WishIndexFilters = require( '../../../modules/wish-index/WishIndexFilters.vue' );
const FocusAreasFilter = require( '../../../modules/wish-index/FocusAreasFilter.vue' );
const StatusesFilter = require( '../../../modules/wish-index/StatusesFilter.vue' );
const TagsField = require( '../../../modules/common/TagsField.vue' );

const availableTags = [
	{ value: 'multimedia', label: 'Multimedia' },
	{ value: 'wikisource', label: 'Wikisource' }
];

describe( 'WishIndexFilters', () => {
	let wrapper;

	beforeEach( () => {
		mockMwConfigGet( {
			focusareasData: {
				FA1: 'Focus Area 1',
				FA2: 'Focus Area 2'
			}
		} );
	} );

	afterEach( () => {
		if ( wrapper ) {
			wrapper.unmount();
			wrapper = null;
		}
	} );

	it( 'should submit empty filters after clearing all filters', async () => {
		const submissions = [];

		wrapper = mount( WishIndexFilters, {
			props: {
				tags: [ 'multimedia' ],
				availableTags,
				statuses: [ 'submitted' ],
				focusareas: [ 'FA1' ],
				onSubmit: ( filters ) => submissions.push( JSON.parse( JSON.stringify( filters ) ) )
			}
		} );

		await wrapper.find( '.ext-communityrequests-wishes--filters-buttons button' )
			.trigger( 'click' );
		await wrapper.vm.$nextTick();

		expect( submissions ).toHaveLength( 1 );
		const submittedFilters = submissions[ 0 ];
		expect( submittedFilters.tags ).toEqual( [] );
		expect( submittedFilters.statuses ).toEqual( [] );
		expect( submittedFilters.focusareas ).toEqual( [] );
	} );

	it( 'should submit selected filters', async () => {
		wrapper = mount( WishIndexFilters, {
			props: {
				tags: [],
				availableTags,
				statuses: [],
				focusareas: []
			}
		} );

		wrapper.findComponent( TagsField ).vm.$emit( 'update:tags', [ 'wikisource' ] );
		wrapper.findComponent( StatusesFilter ).vm.$emit( 'update:statuses', [ 'submitted' ] );
		wrapper.findComponent( FocusAreasFilter ).vm.$emit( 'update:focusareas', [ 'FA2' ] );
		await wrapper.vm.$nextTick();
		await wrapper.find( 'button[type="submit"]' ).trigger( 'click' );

		const submittedFilters = wrapper.emitted( 'submit' )[ 0 ][ 0 ];
		expect( submittedFilters.tags ).toEqual( [ 'wikisource' ] );
		expect( submittedFilters.statuses ).toEqual( [ 'submitted' ] );
		expect( submittedFilters.focusareas ).toEqual( [ 'FA2' ] );
	} );
} );
