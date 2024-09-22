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
const Wish = require( '../common/Wish.js' );

module.exports = exports = defineComponent( {
	name: 'StatusSection',
	components: {
		CdxField,
		CdxSelect
	},
	props: {
		status: { type: String, default: Wish.STATUS_SUBMITTED },
		disabled: { type: Boolean, default: false }
	},
	emits: [
		'update:status'
	],
	data( props ) {
		return {
			statusValue: props.status,
			statusOptions: [
				{ label: mw.msg( 'communityrequests-status-draft' ), value: Wish.STATUS_DRAFT },
				{ label: mw.msg( 'communityrequests-status-submitted' ), value: Wish.STATUS_SUBMITTED },
				{ label: mw.msg( 'communityrequests-status-open' ), value: Wish.STATUS_OPEN },
				{ label: mw.msg( 'communityrequests-status-in-progress' ), value: Wish.STATUS_IN_PROGRESS },
				{ label: mw.msg( 'communityrequests-status-delivered' ), value: Wish.STATUS_DELIVERED },
				{ label: mw.msg( 'communityrequests-status-blocked' ), value: Wish.STATUS_BLOCKED },
				{ label: mw.msg( 'communityrequests-status-archived' ), value: Wish.STATUS_ARCHIVED }
			]
		};
	}
} );
</script>
