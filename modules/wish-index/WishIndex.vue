<template>
	<cdx-field>
		<wish-index-filters
			v-if="wishesData.showfilters"
			:tags="wishlistFilters.tags"
			:statuses="wishlistFilters.statuses"
			:focusareas="wishlistFilters.focusareas"
			@submit="updateWishIndexTable"
		></wish-index-filters>
		<wish-index-table
			v-bind="wishesData"
			:tags="wishlistFilters.tags"
			:statuses="wishlistFilters.statuses"
			:focusareas="wishlistFilters.focusareas"
		></wish-index-table>
	</cdx-field>
</template>

<script>
const { defineComponent, onBeforeMount, reactive, watch, Reactive, Ref } = require( 'vue' );
const { CdxField } = require( '../codex.js' );
const WishIndexFilters = require( './WishIndexFilters.vue' );
const WishIndexTable = require( './WishIndexTable.vue' );
const { CommunityRequestsWishIndexPage } = require( '../common/config.json' );

module.exports = exports = defineComponent( {
	name: 'WishIndex',
	components: {
		CdxField,
		WishIndexFilters,
		WishIndexTable
	},
	props: {
		// eslint-disable-next-line vue/no-unused-properties -- Used in a mixin
		tags: { type: Array, default: () => [] },
		// eslint-disable-next-line vue/no-unused-properties -- Used in a mixin
		statuses: { type: Array, default: () => [] },
		// eslint-disable-next-line vue/no-unused-properties -- Used in a mixin
		focusareas: { type: Array, default: () => [] }
	},
	setup( props ) {
		/**
		 * Reactive object representing the filters being set on wishlist table.
		 *
		 * @type {Reactive<Object>}
		 */
		const wishlistFilters = reactive( Object.assign( {}, props ) );

		/**
		 * Update wishlist table filters when filter form is submitted
		 *
		 * @param {Ref<Object[]>} filters
		 */
		function updateWishIndexTable( filters ) {
			wishlistFilters.tags = filters.tags;
			wishlistFilters.statuses = filters.statuses;
			wishlistFilters.focusareas = filters.focusareas;
		}

		// Add URL parameter reactivity for filters on the wish index page.
		const isIndexPage = mw.config.get( 'wgPageName' ) ===
			mw.Title.newFromText( CommunityRequestsWishIndexPage ).getPrefixedDb();
		if ( isIndexPage ) {
			watch( wishlistFilters, () => {
				const params = new URLSearchParams( window.location.search );
				for ( const filterKey of [ 'tags', 'statuses', 'focusareas' ] ) {
					if ( wishlistFilters[ filterKey ].length ) {
						params.set( filterKey, wishlistFilters[ filterKey ].join( '|' ) );
					} else {
						params.delete( filterKey );
					}
				}

				const newUrl = `${ window.location.pathname }?${ params.toString() }`.replace( /\?$/, '' );
				history.replaceState( null, '', newUrl );
			}, { deep: true } );

			// Before mounting, check URL parameters and set filters accordingly.
			onBeforeMount( () => {
				const params = new URLSearchParams( window.location.search );
				for ( const filterKey of [ 'tags', 'statuses', 'focusareas' ] ) {
					if ( params.has( filterKey ) ) {
						wishlistFilters[ filterKey ] = params.get( filterKey ).split( '|' );
					}
				}
			} );
		}

		return {
			wishesData: mw.config.get( 'wishesData' ),
			wishlistFilters,
			updateWishIndexTable
		};
	}
} );
</script>
