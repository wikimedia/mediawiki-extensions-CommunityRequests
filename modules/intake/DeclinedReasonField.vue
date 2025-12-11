<template>
	<cdx-field class="ext-communityrequests-intake__decline-reason">
		<cdx-select
			v-model:selected="reasonValue"
			:menu-items="reasonOptions"
			name="declined-reason"
		></cdx-select>
		<template #label>
			{{ $i18n( 'communityrequests-declined-reason' ) }}
		</template>
		<template #description>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<span v-html="declinedReasonDescriptionText"></span>
		</template>
	</cdx-field>
	<cdx-field>
		<cdx-text-input
			v-model="additionalReasonValue"
			name="declined-additional-reason"
		></cdx-text-input>
		<template #label>
			{{ $i18n( 'communityrequests-declined-additional-reason' ) }}
		</template>
	</cdx-field>
</template>

<script>
const { defineComponent, ref, Ref } = require( 'vue' );
const { CdxField, CdxSelect, CdxTextInput } = require( '../codex.js' );
const { CommunityRequestsDeclineTemplate } = require( '../common/config.json' );

module.exports = exports = defineComponent( {
	name: 'DeclinedReasonField',
	components: {
		CdxSelect,
		CdxField,
		CdxTextInput
	},
	props: {
		reason: { type: String, default: '' },
		additionalReason: { type: String, default: '' }
	},
	setup( props ) {
		/**
		 * The declined reason options of the wish.
		 *
		 * @type {Ref<Array>}
		 */
		const reasonOptions = ref( [] );
		const reasonValue = ref( props.reason );
		const additionalReasonValue = ref( props.additionalReason );

		const declinedReasonDescriptionText = mw.message(
			'communityrequests-declined-reason-field-description',
			CommunityRequestsDeclineTemplate
		).parse();

		function fetchDeclinedReasons() {
			return new mw.Api().get( {
				action: 'templatedata',
				titles: CommunityRequestsDeclineTemplate,
				uselang: mw.config.get( 'wgUserLanguage' ),
				format: 'json'
			} ).then( ( response ) => Object.values( response.pages )[ 0 ] );
		}

		// Set the options for declined reasons dropdown retrieved from templatedata api request.
		fetchDeclinedReasons().then( ( data ) => {
			reasonOptions.value = Object.values( data.params[ 1 ].suggestedvalues )
				.map( ( reason ) => ( {
					label: reason,
					value: reason
				} ) );
			// Set reasonValue to first reasonOptions value, if it's empty
			reasonValue.value = reasonValue.value || reasonOptions.value[ 0 ].value;

		} ).catch( () => {} );

		return {
			declinedReasonDescriptionText,
			reasonValue,
			additionalReasonValue,
			reasonOptions
		};
	}
} );
</script>
