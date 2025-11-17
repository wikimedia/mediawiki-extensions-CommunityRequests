<template>
	<cdx-field class="ext-communityrequests-intake__status">
		<cdx-select
			v-model:selected="statusValue"
			:menu-items="statusOptions"
			@update:selected="$emit( 'update:status', $event )"
		></cdx-select>
		<template #label>
			{{ $i18n( 'communityrequests-status' ) }}
		</template>
	</cdx-field>
</template>

<script>
const { defineComponent, ref, Ref } = require( 'vue' );
const { CdxField, CdxSelect } = require( '../codex.js' );
const { CommunityRequestsStatuses } = require( '../common/config.json' );

module.exports = exports = defineComponent( {
	name: 'StatusField',
	components: {
		CdxField,
		CdxSelect
	},
	props: {
		status: { type: String, required: true },
		entityType: { type: String, required: true }
	},
	emits: [
		'update:status'
	],
	setup( props ) {
		/**
		 * The status of the wish.
		 *
		 * @type {Ref<string>}
		 */
		const statusValue = ref( props.status );

		/**
		 * The options for the status dropdown.
		 *
		 * @type {Array}
		 */
		const statusOptions = Object.keys( CommunityRequestsStatuses )
			.map( ( status ) => ( {
				// Messages are configurable. By default, they include:
				// * communityrequests-status-wish-draft
				// * communityrequests-status-wish-submitted
				// * communityrequests-status-wish-open
				// * communityrequests-status-wish-in-progress
				// * communityrequests-status-wish-delivered
				// * communityrequests-status-wish-blocked
				// * communityrequests-status-wish-archived
				// * communityrequests-status-focus-area-draft
				// * communityrequests-status-focus-area-submitted
				// * communityrequests-status-focus-area-open
				// * communityrequests-status-focus-area-in-progress
				// * communityrequests-status-focus-area-delivered
				// * communityrequests-status-focus-area-blocked
				// * communityrequests-status-focus-area-archived
				label: mw.msg( 'communityrequests-status-' + props.entityType + '-' + status ),
				value: status
			} ) );

		return {
			statusValue,
			statusOptions
		};
	}
} );
</script>
