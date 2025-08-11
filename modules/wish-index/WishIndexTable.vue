<template>
	<cdx-table
		:caption="$i18n( 'communityrequests-wishes-table-caption' ).text()"
		:columns="columns"
		:data="data"
		:sort="tableSort"
		:hide-caption="true"
		:show-vertical-borders="true"
		:paginate="true"
		:server-pagination="true"
		:total-rows="totalWishCount"
		:pagination-size-default="limit"
		:pagination-size-options="paginationSizeOptions"
		:pending="pending"
		@update:sort="onUpdateSort"
		@load-more="onLoadMore"
	>
		<template #item-title="{ item, row }">
			<a
				class="ext-communityrequests-wishes--title-link"
				:href="mw.Title.makeTitle( row.crwns, row.crwtitle ).getUrl()"
			>
				{{ item }}
			</a>
		</template>
		<template #item-focusarea="{ item, row }">
			<a
				v-if="item"
				class="ext-communityrequests-wishes--focusarea-link"
				:href="mw.Title.makeTitle( row.crfans, row.crfatitle ).getUrl()"
			>
				{{ row.focusareatitle }}
			</a>
			<span v-else>{{ $i18n( 'communityrequests-focus-area-unassigned' ).text() }}</span>
		</template>
		<template #item-status="{ item }">
			<cdx-info-chip>{{ wishStatus( item ) }}</cdx-info-chip>
		</template>
		<template #item-created="{ item }">
			{{ formatDate( Date.parse( item ) ) }}
		</template>
		<template v-if="error" #tbody>
			<tr>
				<td :colspan="columns.length">
					<cdx-message type="error" inline>
						<!-- eslint-disable-next-line vue/no-v-html -->
						<div v-html="error"></div>
					</cdx-message>
				</td>
			</tr>
		</template>
	</cdx-table>
</template>

<script>
const { defineComponent, computed, ref, ComputedRef, Ref } = require( 'vue' );
const { CdxInfoChip, CdxMessage, CdxTable } = require( '../codex.js' );
const { formatDate } = require( 'mediawiki.DateFormatter' );
const api = new mw.Api();

/**
 * @typedef {Object} TableColumn
 * @see https://doc.wikimedia.org/codex/latest/components/types-and-constants.html#tablecolumn
 */
/**
 * @typedef {Object} TableSort
 * @see https://doc.wikimedia.org/codex/latest/components/types-and-constants.html#tablesort
 */
/**
 * @typedef {Object} TablePaginationSizeOption
 * @see https://doc.wikimedia.org/codex/latest/components/types-and-constants.html#tablepaginationsizeoption
 */

/**
 * Table columns configuration.
 *
 * @type {Object<TableColumn>}
 */
const columnsConfig = {
	status: {
		id: 'status',
		label: mw.msg( 'communityrequests-wishes-status-header' )
	},
	type: {
		id: 'type',
		label: mw.msg( 'communityrequests-wishes-type-header' )
	},
	title: {
		id: 'title',
		label: mw.msg( 'communityrequests-wishes-title-header' ),
		allowSort: true
	},
	focusarea: {
		id: 'focusarea',
		label: mw.msg( 'communityrequests-wishes-focusarea-header' )
	},
	projects: {
		id: 'projects',
		label: mw.msg( 'communityrequests-wishes-projects-header' ),
		allowSort: true
	},
	votecount: {
		id: 'votecount',
		label: mw.msg( 'communityrequests-wishes-votecount-header' ),
		allowSort: true
	},
	created: {
		id: 'created',
		label: mw.msg( 'communityrequests-wishes-created-header' ),
		allowSort: true
	},
	updated: {
		id: 'updated',
		label: mw.msg( 'communityrequests-wishes-updated-header' ),
		allowSort: true
	}
};

module.exports = exports = defineComponent( {
	name: 'WishIndexTable',
	components: {
		CdxInfoChip,
		CdxMessage,
		CdxTable
	},
	props: {
		lang: { type: String, default: mw.config.get( 'wgContentLanguage' ) },
		sort: { type: String, default: 'created' },
		dir: { type: String, default: 'descending' },
		limit: { type: Number, default: 10 }
	},
	setup( props ) {
		// Reactive properties

		/**
		 * Currently displayed columns.
		 *
		 * @type {Ref<Object[]>}
		 */
		const columns = ref( [
			columnsConfig.title,
			columnsConfig.focusarea,
			columnsConfig.votecount,
			columnsConfig.created,
			columnsConfig.status
		] );

		/**
		 * Data for the table.
		 *
		 * @type {Ref<Object[]>}
		 */
		const data = ref( [] );

		/**
		 * Total count of wishes for pagination.
		 *
		 * @type {Ref<number>}
		 */
		const totalWishCount = ref( 0 );

		/**
		 * Pending state for the table data.
		 *
		 * @type {Ref<boolean>}
		 */
		const pending = ref( true );

		/**
		 * The column and direction to sort by.
		 *
		 * @type {Ref<TableSort>}
		 */
		const tableSort = ref( { [ props.sort ]: props.dir === 'ascending' ? 'asc' : 'desc' } );

		/**
		 * The direction of the pagination.
		 *
		 * @todo Currently is always 'forward' (T401027)
		 * @type {Ref<'forward'|'backward'>}
		 */
		const direction = ref( 'forward' );

		/**
		 * The number of rows to display per page.
		 *
		 * @type {Ref<number>}
		 */
		const limit = ref( props.limit );

		/**
		 * Error message from the API, if any.
		 *
		 * @type {Ref<string>}
		 */
		const error = ref( '' );

		// Computed properties

		/**
		 * The ID of the column we're sorting by, which is also
		 * passed to the API as the `crwsort` parameter.
		 *
		 * @type {ComputedRef<string>}
		 */
		const apiSort = computed( () => Object.keys( tableSort.value )[ 0 ] );

		/**
		 * The value for the `crwdir` parameter in the API query,
		 * taking into account the current sort direction.
		 *
		 * @type {ComputedRef<'ascending'|'descending'>}
		 */
		const apiDir = computed( () => {
			const tableSortDir = tableSort.value[ apiSort.value ];
			if ( direction.value === 'backward' ) {
				return tableSortDir === 'asc' ? 'descending' : 'ascending';
			}
			return tableSortDir === 'asc' ? 'ascending' : 'descending';
		} );

		// Non-reactive local variables

		/**
		 * FIXME: Codex seems to do the math wrong when changing pagination size
		 * with the `serverPagination` prop set. For now, we only allow the
		 * given `limit` and hide the pagination size selector.
		 *
		 * @type {TablePaginationSizeOption[]}
		 */
		const paginationSizeOptions = [ { value: props.limit } ];
		const previousContinueValues = [];
		let currentContinueValue = null;
		let nextContinueValue = null;
		let currentOffset = 0;

		// Functions

		/**
		 * Fetch wishes data from the API and populate the table.
		 *
		 * @return {Promise<void>}
		 */
		async function fetchWishes() {
			pending.value = true;

			const params = {
				action: 'query',
				format: 'json',
				list: 'communityrequests-wishes',
				crwprop: columns.value.map( ( column ) => column.id ).join( '|' ),
				crwlang: props.lang,
				crwsort: apiSort.value,
				crwdir: apiDir.value,
				crwlimit: limit.value,
				crwcount: 1
			};
			if ( currentContinueValue ) {
				params.crwcontinue = currentContinueValue;
			}

			const response = await api.get( params ).catch( ( _, errorObj ) => {
				error.value = api.getErrorMessage( errorObj ).html();
				return {};
			} );

			const wishes = response.query && response.query[ 'communityrequests-wishes' ];
			if ( wishes ) {
				data.value = direction.value === 'forward' ? wishes : wishes.reverse();
				totalWishCount.value = response.query[ 'communityrequests-wishes-metadata' ].count;
				nextContinueValue = response.continue ? response.continue.crwcontinue : null;
			} else {
				data.value = [];
				totalWishCount.value = 0;
			}

			pending.value = false;
		}

		/**
		 * Handle sorting changes in the table.
		 *
		 * @param {TableSort} newSort
		 */
		function onUpdateSort( newSort ) {
			tableSort.value = newSort;
			fetchWishes();
		}

		/**
		 * Handle pagination.
		 *
		 * @param {number} offset
		 * @param {number} rows
		 */
		function onLoadMore( offset, rows ) {
			if ( offset === 0 ) {
				currentOffset = 0;
				previousContinueValues.length = 0;
				currentContinueValue = null;
				nextContinueValue = null;
			} else if ( offset > currentOffset ) {
				previousContinueValues.push( currentContinueValue );
				currentContinueValue = nextContinueValue;
			} else {
				currentContinueValue = previousContinueValues.pop();
			}
			currentOffset = offset;
			limit.value = rows;
			fetchWishes();
		}

		/**
		 * Get the localized status of a wish.
		 *
		 * @param {string} status
		 * @return {string}
		 */
		function wishStatus( status ) {
			// Messages used here include:
			// * communityrequests-status-draft
			// * communityrequests-status-submitted
			// * communityrequests-status-open
			// * communityrequests-status-in-progress
			// * communityrequests-status-delivered
			// * communityrequests-status-blocked
			// * communityrequests-status-archived
			// * communityrequests-status-unknown
			return mw.message( `communityrequests-status-${ status }` ).text();
		}

		fetchWishes();

		return {
			columns,
			data,
			tableSort,
			error,
			pending,
			totalWishCount,
			paginationSizeOptions,
			mw,
			formatDate,
			onUpdateSort,
			onLoadMore,
			wishStatus
		};
	}
} );
</script>

<style lang="less">
// Hide the pagination size selector; Codex does not handle it correctly with server pagination.
.cdx-table-pager__start {
	display: none;
}
// Hide the "last page" button until T401027 is resolved.
.cdx-button.cdx-table-pager__button-last {
	display: none;
}
</style>
