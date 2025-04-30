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
const config = require( '../common/config.json' );

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
		const radios = config.CommunityRequestsWishTypes.map(
			( type ) => ( {
				// Messages are configurable. By default, they include:
				// * communitywishlist-wishtype-feature-label
				// * communitywishlist-wishtype-bug-label
				// * communitywishlist-wishtype-change-label
				// * communitywishlist-wishtype-unknown-label
				label: mw.msg( type.label + '-label' ),
				// Messages are configurable. By default, they include:
				// * communitywishlist-wishtype-feature-description
				// * communitywishlist-wishtype-bug-description
				// * communitywishlist-wishtype-change-description
				// * communitywishlist-wishtype-unknown-description
				description: mw.msg( type.label + '-description' ),
				value: type.id
			} )
		);

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
