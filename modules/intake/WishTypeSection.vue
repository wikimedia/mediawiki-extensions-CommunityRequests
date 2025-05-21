<template>
	<cdx-field
		class="ext-communityrequests-intake__type"
		:is-fieldset="true"
		:status="status"
		:messages="messages"
	>
		<cdx-radio
			v-for="radio in radios"
			:key="'radio-' + radio.value"
			:model-value="typeValue"
			name="type"
			:input-value="radio.value"
			@change="$emit( 'update:type', $event.target.value )"
		>
			{{ radio.label }}
			<template #description>
				{{ radio.description }}
			</template>
		</cdx-radio>
		<template #label>
			{{ $i18n( 'communityrequests-wishtype-label' ).text() }}
		</template>
		<template #description>
			{{ $i18n( 'communityrequests-wishtype-description' ).text() }}
		</template>
	</cdx-field>
</template>

<script>
const { computed, defineComponent, ref, ComputedRef } = require( 'vue' );
const { CdxField, CdxRadio } = require( '../codex.js' );
const { CommunityRequestsWishTypes } = require( '../common/config.json' );

module.exports = exports = defineComponent( {
	name: 'TypeSection',
	components: {
		CdxField,
		CdxRadio
	},
	props: {
		type: { type: String, required: true },
		status: { type: String, default: 'default' }
	},
	emits: [ 'update:type' ],
	setup( props ) {
		const typeValue = ref( props.type );
		/**
		 * Status messages for the field.
		 *
		 * @type {ComputedRef<Object>}
		 */
		const messages = computed(
			() => props.status === 'error' ?
				{ error: mw.msg( 'communityrequests-wishtype-error' ) } :
				{}
		);

		const radios = Object.keys( CommunityRequestsWishTypes ).map(
			( key ) => ( {
				// Messages are configurable. By default, they include:
				// * communityrequests-wishtype-feature-label
				// * communityrequests-wishtype-bug-label
				// * communityrequests-wishtype-change-label
				// * communityrequests-wishtype-unknown-label
				label: mw.msg( CommunityRequestsWishTypes[ key ].label + '-label' ),
				// Messages are configurable. By default, they include:
				// * communityrequests-wishtype-feature-description
				// * communityrequests-wishtype-bug-description
				// * communityrequests-wishtype-change-description
				// * communityrequests-wishtype-unknown-description
				description: mw.msg( CommunityRequestsWishTypes[ key ].label + '-description' ),
				value: key
			} )
		);

		return {
			typeValue,
			messages,
			radios
		};
	}
} );
</script>
