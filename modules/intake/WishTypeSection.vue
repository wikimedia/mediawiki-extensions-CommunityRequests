<template>
	<section class="wishlist-intake-type">
		<cdx-field
			:is-fieldset="true"
			:status="status"
			:messages="messages"
			:disabled="disabled"
		>
			<cdx-radio
				v-for="radio in radios"
				:key="'radio-' + radio.value"
				:model-value="type"
				name="radio-group-descriptions"
				:input-value="radio.value"
				@input="$emit( 'update:type', $event.target.value )"
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
	</section>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxField, CdxRadio } = require( '@wikimedia/codex' );
const Wish = require( '../common/Wish.js' );

module.exports = exports = defineComponent( {
	name: 'TypeSection',
	components: {
		CdxField,
		CdxRadio
	},
	props: {
		type: { type: String, default: null },
		status: { type: String, default: 'default' },
		disabled: { type: Boolean, default: false }
	},
	emits: [
		'update:type'
	],
	setup() {
		const radios = [
			{
				label: mw.msg( 'communityrequests-wishtype-feature-label' ),
				description: mw.msg( 'communityrequests-wishtype-feature-description' ),
				value: Wish.TYPE_FEATURE
			},
			{
				label: mw.msg( 'communityrequests-wishtype-bug-label' ),
				description: mw.msg( 'communityrequests-wishtype-bug-description' ),
				value: Wish.TYPE_BUG
			},
			{
				label: mw.msg( 'communityrequests-wishtype-change-label' ),
				description: mw.msg( 'communityrequests-wishtype-change-description' ),
				value: Wish.TYPE_CHANGE
			},
			{
				label: mw.msg( 'communityrequests-wishtype-unknown-label' ),
				description: mw.msg( 'communityrequests-wishtype-unknown-description' ),
				value: Wish.TYPE_UNKNOWN
			}
		];

		return {
			radios
		};
	},
	data() {
		return {
			messages: {}
		};
	},
	watch: {
		status: {
			handler( newStatus ) {
				if ( newStatus === 'error' ) {
					this.messages = { error: mw.msg( 'communityrequests-wishtype-error' ) };
				} else {
					this.messages = {};
				}
			}
		}
	}
} );
</script>
