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
		<template #description>
			<!-- TODO: i18n, WikimediaMessages override -->
			Only staff can change the status of a wish.
		</template>
		<!-- eslint-disable-next-line vue/html-self-closing -->
		<input
			:value="status"
			type="hidden"
			name="status" />
	</cdx-field>
</template>

<script>
const { defineComponent, ref, Ref } = require( 'vue' );
const { CdxField, CdxSelect } = require( '../codex.js' );
const { CommunityRequestsStatuses } = require( '../common/config.json' );

module.exports = exports = defineComponent( {
	name: 'StatusSection',
	components: {
		CdxField,
		CdxSelect
	},
	props: {
		status: { type: String, required: true }
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
				// * communityrequests-status-draft
				// * communityrequests-status-submitted
				// * communityrequests-status-open
				// * communityrequests-status-in-progress
				// * communityrequests-status-delivered
				// * communityrequests-status-blocked
				// * communityrequests-status-archived
				label: mw.msg( CommunityRequestsStatuses[ status ].label ),
				value: status
			} ) );

		return {
			statusValue,
			statusOptions
		};
	}
} );
</script>
