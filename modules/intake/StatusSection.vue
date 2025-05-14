<template>
	<section class="wishlist-intake-status">
		<cdx-field :disabled="disabled">
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
		</cdx-field>
	</section>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxField, CdxSelect } = require( '@wikimedia/codex' );
const statuses = require( '../common/config.json' ).CommunityRequestsStatuses;

module.exports = exports = defineComponent( {
	name: 'StatusSection',
	components: {
		CdxField,
		CdxSelect
	},
	props: {
		status: { type: Number, default: statuses.submitted.id },
		disabled: { type: Boolean, default: false }
	},
	emits: [
		'update:status'
	],
	data( props ) {
		return {
			statusValue: props.status,
			statusOptions: Object.keys( statuses )
				.map( ( status ) => ( {
					// Messages are configurable. By default, they include:
					// * communityrequests-status-draft
					// * communityrequests-status-submitted
					// * communityrequests-status-open
					// * communityrequests-status-in-progress
					// * communityrequests-status-delivered
					// * communityrequests-status-blocked
					// * communityrequests-status-archived
					label: mw.msg( statuses[ status ].label ),
					value: statuses[ status ].id
				} ) )
		};
	}
} );
</script>
