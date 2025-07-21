<template>
	<cdx-field class="ext-communityrequests-intake__focus-area">
		<cdx-select
			v-model:selected="focusAreaValue"
			:menu-items="focusAreaOptions"
			@update:selected="$emit( 'update:focus-area', $event )"
		></cdx-select>
		<template #label>
			{{ $i18n( 'communityrequests-intake-focus-area' ).text() }}
		</template>
	</cdx-field>
</template>

<script>
const { defineComponent, ref, Ref } = require( 'vue' );
const { CdxField, CdxSelect } = require( '../codex.js' );

module.exports = exports = defineComponent( {
	name: 'FocusAreaSection',
	components: {
		CdxField,
		CdxSelect
	},
	props: {
		focusArea: { type: String, default: null }
	},
	emits: [
		'update:focus-area'
	],
	setup( props ) {
		const focusAreaData = mw.config.get( 'intakeFocusAreas' );
		const wikitextVals = Object.keys( focusAreaData );

		/**
		 * The currently selected focus area.
		 *
		 * @type {Ref<string>}
		 */
		const focusAreaValue = ref( props.focusArea );

		/**
		 * Menu items for the Select component.
		 *
		 * @type {Ref<Array>}
		 */
		const focusAreaOptions = ref(
			[ {
				label: mw.msg( 'communityrequests-focus-area-unassigned' ),
				value: null
			} ].concat(
				wikitextVals.map( ( id ) => ( {
					label: focusAreaData[ id ],
					value: id
				} ) )
			)
		);

		return {
			focusAreaValue,
			focusAreaOptions
		};
	}
} );
</script>
