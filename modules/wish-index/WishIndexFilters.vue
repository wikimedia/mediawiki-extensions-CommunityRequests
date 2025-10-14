<template>
	<cdx-accordion separation="outline" open>
		<template #title>
			{{ $i18n( 'communityrequests-wishes-filters-header' ) }}
		</template>
		<form class="ext-communityrequests-wishes--filters">
			<div class="ext-communityrequests-wishes--filters-fields">
				<tags-field
					v-model:tags="filters.tags"
					:clear-field="clearFilters"
				></tags-field>
				<statuses-filter
					v-model:statuses="filters.statuses"
					:clear-field="clearFilters"
				></statuses-filter>
				<focus-areas-filter
					v-model:focusareas="filters.focusareas"
					:clear-field="clearFilters"
				></focus-areas-filter>
			</div>
			<div class="ext-communityrequests-wishes--filters-buttons">
				<cdx-button
					weight="quiet"
					:aria-label="$i18n( 'communityrequests-wishes-filters-clear' ).text()"
					@click.prevent="onClearAllFilters"
				>
					{{ $i18n( 'communityrequests-wishes-filters-clear' ).text() }}
				</cdx-button>
				<cdx-button
					:aria-label="$i18n( 'submit' ).text()"
					type="submit"
					@click.prevent="$emit( 'submit', filters )"
				>
					{{ $i18n( 'communityrequests-update-label' ) }}
				</cdx-button>
			</div>
		</form>
	</cdx-accordion>
</template>

<script>
const { defineComponent, nextTick, reactive, ref, Reactive, Ref } = require( 'vue' );
const { CdxAccordion, CdxButton } = require( '../codex.js' );
const FocusAreasFilter = require( './FocusAreasFilter.vue' );
const StatusesFilter = require( './StatusesFilter.vue' );
const TagsField = require( '../common/TagsField.vue' );

module.exports = exports = defineComponent( {
	name: 'WishIndexFilters',
	components: {
		CdxAccordion,
		CdxButton,
		FocusAreasFilter,
		StatusesFilter,
		TagsField
	},
	props: {
		// eslint-disable-next-line vue/no-unused-properties -- Used in a mixin
		tags: { type: Array, default: () => [] },
		// eslint-disable-next-line vue/no-unused-properties -- Used in a mixin
		statuses: { type: Array, default: () => [] },
		// eslint-disable-next-line vue/no-unused-properties -- Used in a mixin
		focusareas: { type: Array, default: () => [] }
	},
	emits: [ 'submit' ],
	setup( props, { emit } ) {
		/**
		 * Reactive object representing the filters being set.
		 *
		 * @type {Reactive<Object>}
		 */
		const filters = reactive( Object.assign( {}, props ) );

		/**
		 * Flag to clear all filter fields.
		 *
		 * @type {Ref<boolean>}
		 */
		const clearFilters = ref( false );

		/**
		 * Clear all filter fields.
		 */
		function onClearAllFilters() {
			clearFilters.value = true;
			nextTick( () => {
				emit( 'submit', filters );
				clearFilters.value = false;
			} );
		}

		return {
			clearFilters,
			filters,
			onClearAllFilters
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.cdx-accordion {
	margin-bottom: @spacing-100;
}

.ext-communityrequests-wishes--filters {

	&-fields {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: @spacing-100;
		/* Single column on mobile. */
		@media screen and ( max-width: @max-width-breakpoint-mobile ) {
			& {
				grid-template-columns: none;
			}
		}

		// Disable margin-top on all nested fields.
		.cdx-field {
			margin-top: 0;
		}
	}
	&-buttons {
		float: right;
		margin-bottom: @spacing-100;
	}
}
</style>
