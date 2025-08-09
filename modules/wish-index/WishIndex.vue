<template>
	<cdx-field>
		<wish-index-filters
			v-if="wishesData.showfilters"
			@submit="updateWishIndexTable"
		></wish-index-filters>
		<wish-index-table
			v-bind="wishesData"
			:focusareas="wishlistFilters.focusareas"
			:statuses="wishlistFilters.statuses"
			:tags="wishlistFilters.tags"
		></wish-index-table>
	</cdx-field>
</template>

<script>
const { defineComponent, reactive, Ref } = require( 'vue' );
const { CdxField } = require( '../codex.js' );
const WishIndexFilters = require( './WishIndexFilters.vue' );
const WishIndexTable = require( './WishIndexTable.vue' );
module.exports = exports = defineComponent( {
	name: 'WishIndex',
	components: {
		CdxField,
		WishIndexFilters,
		WishIndexTable
	},
	props: {
		// eslint-disable-next-line vue/no-unused-properties -- Used in a mixin
		focusareas: { type: Array, default: () => [] },
		// eslint-disable-next-line vue/no-unused-properties -- Used in a mixin
		statuses: { type: Array, default: () => [] },
		// eslint-disable-next-line vue/no-unused-properties -- Used in a mixin
		tags: { type: Array, default: () => [] }

	},
	setup( props ) {
		// Reactive properties
		/**
		 * Reactive object representing the filters being set on wishlist table.
		 *
		 * @type {Ref<Object>}
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

		return {
			wishesData: mw.config.get( 'wishesData' ),
			wishlistFilters,
			updateWishIndexTable
		};
	}
} );
</script>
