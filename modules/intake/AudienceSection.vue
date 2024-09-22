<template>
	<section class="wishlist-intake-audience">
		<cdx-field
			:status="status"
			:messages="messages"
			:disabled="disabled"
		>
			<cdx-text-input
				:model-value="audience"
				@input="$emit( 'update:audience', $event.target.value.trim() )"
			>
			</cdx-text-input>
			<template #label>
				{{ $i18n( 'communityrequests-audience-label' ).text() }}
			</template>
			<template #description>
				{{ $i18n( 'communityrequests-audience-description' ).text() }}
			</template>
		</cdx-field>
	</section>
</template>

<script>
const { CdxField, CdxTextInput } = require( '@wikimedia/codex' );
const { defineComponent } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'AudienceSection',
	components: {
		CdxField,
		CdxTextInput
	},
	props: {
		audience: { type: String, default: '' },
		status: { type: String, default: 'default' },
		disabled: { type: Boolean, default: false }
	},
	emits: [
		'update:audience'
	],
	data() {
		return {
			messages: {}
		};
	},
	watch: {
		status: {
			handler( newStatus ) {
				if ( newStatus === 'error' ) {
					this.messages = {
						error: mw.msg( 'communityrequests-audience-error', 5, 300 )
					};
				} else {
					this.messages = {};
				}
			}
		}
	}
} );
</script>
