<template>
	<cdx-field
		:is-fieldset="true"
	>
		<cdx-multiselect-lookup
			v-model:input-chips="chips"
			v-model:selected="selection"
			v-model:input-value="inputValue"
			:keep-input-on-selection="true"
			:menu-items="menuItems"
			:menu-config="menuConfig"
			:aria-label="$i18n( 'communityrequests-wishes-filters-focus-areas-label' ).text()"
			@input="onInput"
			@update:selected="onSelection"
			@blur="validateInstantly"
			@keydown.enter="validateInstantly"
		>
		</cdx-multiselect-lookup>
		<template #label>
			{{ $i18n( 'communityrequests-wishes-filters-focus-areas-label' ).text() }}
		</template>
		<input
			:value="focusareas"
			type="hidden"
			name="focusareas"
		>
	</cdx-field>
</template>

<script>
const { defineComponent, nextTick, ref, watch, Ref } = require( 'vue' );
const { CdxField, CdxMultiselectLookup } = require( '../codex.js' );

module.exports = exports = defineComponent( {
	name: 'FocusAreasFilter',
	components: {
		CdxField,
		CdxMultiselectLookup
	},
	props: {
		focusareas: { type: Array, default: () => [] },
		clearField: { type: Boolean, default: false }
	},
	emits: [
		'update:focusareas'
	],
	setup( props, { emit } ) {
		const focusareasData = mw.config.get( 'focusareasData' );
		const wikitextVals = Object.keys( focusareasData );
		/**
		 * Menu items for the MultiSelect component.
		 *
		 * @type {Ref<Array>}
		 */
		const focusareasList = wikitextVals.map(
			( id ) => ( {
				label: focusareasData[ id ],
				value: id
			} )
		);

		const chips = ref( focusareasList.filter(
			( focusarea ) => props.focusareas.includes( focusarea.value )
		) );
		const selection = ref( props.focusareas );
		const inputValue = ref( '' );
		const menuItems = ref( focusareasList );
		const menuConfig = {
			visibleItemLimit: 5
		};

		/**
		 * Maybe set a warning message when the user moves out of the field or hits enter.
		 */
		function validateInstantly() {
			// Await nextTick in case the user has selected a menu item via the Enter key - this
			// will ensure the selection ref has been updated.
			nextTick( () => {
				// Clear out the input value which must have been invalid.
				inputValue.value = '';
			} );
		}

		/**
		 * Emit the updated focusareas.
		 */
		function onSelection() {
			if ( selection.value !== null ) {
				emit( 'update:focusareas', selection.value );
			}
			// Reset the search value and menu.
			inputValue.value = '';
			menuItems.value = focusareasList;
		}

		/**
		 * Handle lookup input.
		 *
		 * @param {string} value The new input value
		 */
		function onInput( value ) {
			// Reset menu items if the input was cleared.
			if ( !value ) {
				menuItems.value = focusareasList;
				return;
			}

			// Make sure this data is still relevant first.
			if ( inputValue.value !== value ) {
				return;
			}

			// Update menuItems to tags that match the input value.
			menuItems.value = focusareasList.filter(
				( focusarea ) => focusarea.label.toLowerCase().includes( value.toLowerCase() )
			);
		}

		watch( () => props.clearField, ( newValue ) => {
			if ( newValue ) {
				selection.value = [];
				inputValue.value = '';
				emit( 'update:focusareas', selection.value );
			}
		} );

		return {
			chips,
			selection,
			inputValue,
			menuItems,
			menuConfig,
			validateInstantly,
			onSelection,
			onInput
		};
	}
} );
</script>
