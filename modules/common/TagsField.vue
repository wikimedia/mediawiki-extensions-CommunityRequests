<template>
	<cdx-field
		class="ext-communityrequests-intake__tags"
		:is-fieldset="true"
		:optional="optionalLabel"
		:status="error ? 'error' : 'default'"
		:messages="{ error }"
	>
		<cdx-multiselect-lookup
			v-model:input-chips="chips"
			v-model:selected="selection"
			v-model:input-value="inputValue"
			:keep-input-on-selection="true"
			:menu-items="menuItems"
			:menu-config="menuConfig"
			:aria-label="fieldLabel"
			@input="onInput"
			@update:selected="onSelection"
			@blur="validateInstantly"
			@keydown.enter="validateInstantly"
		>
		</cdx-multiselect-lookup>
		<template #label>
			{{ fieldLabel }}
		</template>
		<template v-if="showDescription" #description>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<span v-html="descriptionHtml"></span>
		</template>
	</cdx-field>
</template>

<script>
const { computed, defineComponent, nextTick, ref, watch } = require( 'vue' );
const { CdxField, CdxMultiselectLookup } = require( '../codex.js' );

module.exports = exports = defineComponent( {
	name: 'TagsField',
	components: {
		CdxField,
		CdxMultiselectLookup
	},
	props: {
		optionalLabel: { type: Boolean, default: false },
		showDescription: { type: Boolean, default: false },
		tags: { type: Array, required: true },
		availableTags: { type: Array, required: true },
		clearField: { type: Boolean, default: false },
		labelMessage: { type: String, required: true },
		descriptionMessage: { type: String, default: '' },
		descriptionParams: { type: Array, default: () => [] },
		error: { type: String, default: '' }
	},
	emits: [
		'update:tags'
	],
	setup( props, { emit } ) {
		const chips = ref(
			props.availableTags.filter( ( tag ) => props.tags.includes( tag.value ) )
		);
		const selection = ref( props.tags );
		const inputValue = ref( '' );
		const menuItems = ref( props.availableTags );
		const fieldLabel = mw.msg( props.labelMessage );
		const descriptionHtml = computed( () => props.showDescription ?
			mw.message( props.descriptionMessage, ...props.descriptionParams ).parse() :
			''
		);
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
		 * Emit the updated tags.
		 */
		function onSelection() {
			if ( selection.value !== null ) {
				emit( 'update:tags', selection.value );
			}
			// Reset the search value and menu. T404767.
			inputValue.value = '';
			menuItems.value = props.availableTags;
		}

		/**
		 * Handle lookup input.
		 *
		 * @param {string} value The new input value
		 */
		function onInput( value ) {
			// Reset menu items if the input was cleared.
			if ( !value ) {
				menuItems.value = props.availableTags;
				return;
			}

			// Make sure this data is still relevant first.
			if ( inputValue.value !== value ) {
				return;
			}

			// Update menuItems to tags that match the input value.
			menuItems.value = props.availableTags.filter(
				( tag ) => tag.label.toLowerCase().includes( value.toLowerCase() )
			);
		}

		watch( () => props.clearField, ( newValue ) => {
			if ( newValue ) {
				selection.value = [];
				inputValue.value = '';
				emit( 'update:tags', selection.value );
			}
		} );

		return {
			chips,
			selection,
			inputValue,
			menuItems,
			menuConfig,
			fieldLabel,
			descriptionHtml,
			validateInstantly,
			onSelection,
			onInput
		};
	}
} );
</script>
