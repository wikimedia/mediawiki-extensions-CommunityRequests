<template>
	<cdx-table
		ref="table"
		:caption="$i18n( 'communityrequests-wishes-table-caption' ).text()"
		:columns="columns"
		:data="data"
		:sort="tableSort"
		:hide-caption="true"
		:show-vertical-borders="true"
		:paginate="data.length > 0"
		:server-pagination="true"
		:total-rows="totalWishCount"
		:pagination-size-default="limit"
		:pagination-size-options="paginationSizeOptions"
		:pending="pending"
		@update:sort="onUpdateSort"
		@load-more="onLoadMore"
	>
		<template #empty-state>
			{{ $i18n( 'communityrequests-wishes-table-empty' ).text() }}
		</template>
		<template #item-title="{ item, row }">
			<a
				:href="mw.Title.makeTitle( row.crwns, row.crwtitle ).getUrl()"
				:lang="getLangAttr( row )"
			>
				<span v-if="focusareas.length === 1">
					{{ item }}
				</span>
				<span v-else class="ext-communityrequests-wishes--title-link">
					{{ item }}
				</span>
			</a>
			<div
				v-if="focusareas.length !== 1"
				class="ext-communityrequests-wishes--focusarea-text"
			>
				<!-- eslint-disable-next-line vue/no-v-html -->
				<span v-html="wishIsInFocusAreaHTML( row )"></span>
			</div>
		</template>
		<template #item-tags="{ item, row }">
			<div
				v-for="( tag, index ) in item.slice( 0, showTagLimit )"
				:key="index"
				class="ext-communityrequests-wishes--tag"
			>
				<cdx-info-chip :status="wishStatusStyle( tag )">
					{{ Util.getTagLabel( tag ) }}
				</cdx-info-chip>
			</div>
			<a
				v-if="item.length > showTagLimit"
				class="ext-communityrequests-wishes--more-tags-link"
				:href="mw.Title.makeTitle( row.crwns, row.crwtitle ).getUrl() + '#tags'"
				:title="mw.language.listToText( item.slice( showTagLimit ).map(
					( t ) => mw.msg( 'quotation-marks', Util.getTagLabel( t ) )
				) )"
			>
				{{ $i18n( 'communityrequests-tags-more', item.length - showTagLimit ).text() }}
			</a>
		</template>
		<template #item-status="{ item }">
			<cdx-info-chip :status="wishStatusStyle( item )">
				{{ Util.wishStatus( item ) }}
			</cdx-info-chip>
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
const { defineComponent, computed, nextTick, ref, ComputedRef, Ref, watch } = require( 'vue' );
const { CdxInfoChip, CdxMessage, CdxTable } = require( '../codex.js' );
const { formatDate } = require( 'mediawiki.DateFormatter' );
const { CommunityRequestsStatuses, CommunityRequestsWishPagePrefix } = require( '../common/config.json' );
const Util = require( '../common/Util.js' );
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
		limit: { type: Number, default: 10 },
		statuses: { type: Array, default: () => [] },
		tags: { type: Array, default: () => [] },
		focusareas: { type: Array, default: () => [] }
	},
	setup( props ) {
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
				label: props.focusareas.length === 1 ?
					mw.msg( 'communityrequests-wishes-title-header' ) :
					mw.msg( 'communityrequests-wishes-title-and-focusarea-header' ),
				allowSort: true
			},
			tags: {
				id: 'tags',
				label: mw.msg( 'communityrequests-wishes-tags-header' )
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

		// Reactive properties

		/**
		 * Used to access public methods in the CdxTable component like onFirst().
		 *
		 * @type {Ref}
		 */
		const table = ref();

		/**
		 * Currently displayed columns.
		 *
		 * @type {Ref<Object[]>}
		 */
		const columns = ref( [
			columnsConfig.title,
			columnsConfig.tags,
			columnsConfig.votecount,
			columnsConfig.created,
			columnsConfig.status
		].filter( ( column ) => !!column ) );

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
		const tableSort = ref( { [ props.sort ]:
			[ 'ascending', 'asc' ].includes( props.dir.toLowerCase() ) ?
				'asc' :
				'desc'
		} );

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
				crwprop: columns.value
					.map( ( column ) => column.id )
					.concat( [ 'baselang' ] )
					.join( '|' ),
				crwlang: props.lang,
				crwsort: apiSort.value,
				crwdir: apiDir.value,
				crwlimit: limit.value,
				crwcount: 1
			};
			if ( props.statuses.length ) {
				params.crwstatuses = props.statuses.join( '|' );
			}
			if ( props.tags.length ) {
				params.crwtags = props.tags.join( '|' );
			}
			if ( props.focusareas.length ) {
				params.crwfocusareas = props.focusareas.join( '|' );
			}
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

			// Fire the wikipage.content hook to trigger dynamic updates, such as MinT translations.
			await nextTick();
			mw.hook( 'wikipage.content' ).fire( mw.util.$content.find( '#mw-content-text' ) );

			// If a section header is being linked to, re-scroll to it after
			// fetching the wish table etc and possibly changing the page height.
			const hashId = window.location.hash.slice( 1 );
			if ( hashId ) {
				const linkedSection = document.getElementById( hashId );
				if ( linkedSection ) {
					linkedSection.scrollIntoView();
				}
			}
		}

		/**
		 * Handle sorting changes in the table.
		 *
		 * @param {TableSort} newSort
		 */
		function onUpdateSort( newSort ) {
			tableSort.value = newSort;
			// Jump to the first page
			table.value.onFirst();
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
		 * Get the focus area text, which includes an HTML link to focus area page.
		 *
		 * @param {Object} row
		 * @return {string}
		 */
		function wishIsInFocusAreaHTML( row ) {
			let focusAreaLabel;
			if ( row.crfatitle ) {
				focusAreaLabel = document.createElement( 'a' );
				focusAreaLabel.href = mw.Title.makeTitle( row.crfans, row.crfatitle ).getUrl();
				focusAreaLabel.textContent = row.focusareatitle;
			} else {
				focusAreaLabel = document.createElement( 'span' );
				focusAreaLabel.className = 'ext-communityrequests-wishes--focusarea-unassigned';
				focusAreaLabel.textContent = mw.message( 'communityrequests-focus-area-unassigned' ).escaped();
			}
			return mw.message( 'communityrequests-wishes-in-focusarea-text', focusAreaLabel ).parse();
		}

		/**
		 * Get the style for the status chip.
		 *
		 * @param {string} status
		 * @return {string}
		 */
		function wishStatusStyle( status ) {
			return CommunityRequestsStatuses[ status ] ?
				CommunityRequestsStatuses[ status ].style :
				'notice';
		}

		/**
		 * Get the appropriate `lang` attribute for the wish title link.
		 *
		 * @param {Object} row
		 * @return {string}
		 */
		function getLangAttr( row ) {
			if ( row.baselang === props.lang ) {
				return row.baselang;
			}
			const langCodeMatches = mw.Title.makeTitle( row.crwns, row.crwtitle )
				.getPrefixedDb()
				.slice( CommunityRequestsWishPagePrefix.length )
				// Strip ID and trailing forward slash.
				.match( /^[0-9]*\/(.*)$/ );
			if ( langCodeMatches && langCodeMatches[ 1 ] ) {
				return langCodeMatches[ 1 ];
			}
			return row.baselang;
		}

		fetchWishes();

		watch( () => [ props.focusareas, props.statuses, props.tags ], () => {
			// Ensure the wishlist is updated when filter props change.
			fetchWishes();

			// Update column label for title/focus area if needed.
			columnsConfig.title.label = props.focusareas.length === 1 ?
				mw.msg( 'communityrequests-wishes-title-header' ) :
				mw.msg( 'communityrequests-wishes-title-and-focusarea-header' );
		} );

		return {
			columns,
			data,
			tableSort,
			error,
			pending,
			table,
			totalWishCount,
			paginationSizeOptions,
			mw,
			formatDate,
			onUpdateSort,
			onLoadMore,
			wishIsInFocusAreaHTML,
			wishStatusStyle,
			getLangAttr,
			Util,
			showTagLimit: 3
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

// Hide the pagination size selector; Codex does not handle it correctly with server pagination.
.cdx-table-pager__start {
	display: none;
}
// Hide the "last page" button until T401027 is resolved.
.cdx-button.cdx-table-pager__button-last {
	display: none;
}

.ext-communityrequests-wishes--tag {
	display: inline-block;

	.cdx-info-chip {
		margin: 0 @spacing-50 @spacing-50 0;
	}
}

.ext-communityrequests-wishes--more-tags-link {
	white-space: nowrap;
}

.ext-communityrequests-wishes {
	&--title-link {
		font-weight: @font-weight-bold;
	}

	&--focusarea-text {
		color: @color-subtle;
	}

	&--focusarea-unassigned {
		color: @color-icon-notice;
	}
}
</style>
