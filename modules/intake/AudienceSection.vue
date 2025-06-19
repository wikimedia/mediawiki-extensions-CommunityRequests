<template>
	<cdx-field
		class="ext-communityrequests-intake__audience"
		:status="status"
		:messages="messages"
	>
		<cdx-text-input
			:model-value="audience"
			name="audience"
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
</template>

<script>
const { CdxField, CdxTextInput } = require( '../codex.js' );
const { defineComponent, ref, watch, Ref } = require( 'vue' );
const audienceMaxChars = mw.config.get( 'intakeAudienceMaxChars' );

module.exports = exports = defineComponent( {
	name: 'AudienceSection',
	components: {
		CdxField,
		CdxTextInput
	},
	props: {
		audience: { type: String, default: '' },
		status: { type: String, default: 'default' }
	},
	emits: [
		'update:audience'
	],
	setup( props ) {
		/**
		 * Error messages to display.
		 *
		 * @type {Ref<Object>}
		 */
		const messages = ref( {} );

		watch( () => props.status, ( newStatus ) => {
			if ( newStatus === 'error' ) {
				messages.value = {
					error: mw.msg( 'communityrequests-audience-error', 5, audienceMaxChars )
				};
			} else {
				messages.value = {};
			}
		} );

		return { messages };
	}
} );
</script>
