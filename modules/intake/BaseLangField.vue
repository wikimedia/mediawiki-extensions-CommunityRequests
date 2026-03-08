<template>
	<cdx-field
		v-if="shouldShowBaseLangField"
		class="ext-communityrequests-intake__baselang"
		:status="status"
		:messages="statusMessage"
	>
		<cdx-combobox
			v-model:selected="selection"
			:menu-items="menuItems"
			:menu-config="menuConfig"
			:clearable="true"
			@input="onInput"
			@change="onChange"
			@clear="onClear"
			@update:selected="onUpdateSelected"
		>
			<template #no-results>
				{{ $i18n( 'communityrequests-baselang-no-results' ).text() }}
			</template>
		</cdx-combobox>
		<template #label>
			{{ formLabel }}
		</template>
	</cdx-field>
	<input
		:value="baselang"
		type="hidden"
		name="baselang"
	>
</template>

<script>
const { computed, defineComponent, ref, ComputedRef, Ref } = require( 'vue' );
const { CdxField, CdxCombobox } = require( '../codex.js' );
const Util = require( '../common/Util.js' );
const languages = require( './languages.json' );

/**
 * @typedef MenuConfig
 * @see https://doc.wikimedia.org/codex/latest/components/types-and-constants.html#menuconfig
 */
/**
 * @typedef MenuItemData
 * @see https://doc.wikimedia.org/codex/latest/components/types-and-constants.html#menuitemdata
 */

module.exports = exports = defineComponent( {
	name: 'BaseLangField',
	components: {
		CdxField,
		CdxCombobox
	},
	props: {
		baselang: { type: String, default: mw.config.get( 'wgUserLanguage' ) },
		status: { type: String, default: 'default' },
		entityType: { type: String, required: true }
	},
	emits: [
		'update:baselang'
	],
	setup( props, { emit } ) {
		/**
		 * Status message.
		 *
		 * @type {ComputedRef<Object>}
		 */
		const statusMessage = computed(
			() => props.status === 'error' ? { error: mw.msg( 'communityrequests-baselang-error' ) } : {}
		);
		/**
		 * @type {MenuItemData[]}
		 */
		const initialMenuItems = Object.keys( languages ).map( ( langCode ) => ( {
			value: labelTemplate( langCode )
		} ) );
		/**
		 * Currently selected langauge, in the form of the language code (e.g. "en" for English).
		 *
		 * @type {Ref<string>}
		 */
		const selection = ref( labelTemplate( props.baselang ) );
		/**
		 * List of visible languages in the dropdown menu.
		 *
		 * @type {Ref<Array>}
		 */
		const menuItems = ref( initialMenuItems );
		/**
		 * @type {MenuConfig}
		 */
		const menuConfig = { visibleItemLimit: 10 };
		/**
		 * Label for the field, which includes the entity type (e.g. "wish" or "focus-area").
		 *
		 * @type {ComputedRef<string>}
		 */
		const formLabel = computed(
			// Messages used here include:
			// * communityrequests-wish-baselang
			// * communityrequests-focus-area-baselang
			() => mw.msg( `communityrequests-${ props.entityType }-baselang` )
		);
		/**
		 * Whether to show the base language field.
		 *
		 * @type {boolean}
		 */
		const shouldShowBaseLangField = (
			props.entityType === 'wish' ? Util.isNewWish() : Util.isNewFocusArea()
		) || mw.config.get( 'intakePageLangRight' );

		/**
		 * Template for menu item labels, which include both the language code and language name.
		 *
		 * @param {string} langCode
		 * @return {string}
		 */
		function labelTemplate( langCode ) {
			if ( !languages[ langCode ] ) {
				return '';
			}
			return `${ langCode } – ${ languages[ langCode ] }`;
		}

		/**
		 * Filter items on input.
		 *
		 * @param {InputEvent} event
		 */
		function onInput( event ) {
			const inputValue = event.target.value.trim().toLowerCase();
			if ( inputValue ) {
				// If there's a value in the input, set menu items to matching items.
				menuItems.value = initialMenuItems.filter(
					( item ) => item.value.toLowerCase().includes( inputValue )
				);
			} else {
				// Otherwise, reset menu items to include all items.
				menuItems.value = initialMenuItems;
			}
		}

		/**
		 * Update selection on change (user types and blurs out of the field).
		 *
		 * @param {Event} event
		 */
		function onChange( event ) {
			onUpdateSelected( labelTemplate( event.target.value ) );
		}

		/**
		 * Emit the language code of the selected item on update.
		 *
		 * @param {string} newValue
		 */
		function onUpdateSelected( newValue ) {
			selection.value = newValue;
			const langCode = newValue.split( ' ' )[ 0 ];
			emit( 'update:baselang', langCode );
		}

		/**
		 * Reset menu items on clear (e.g. when user clicks the "x" button in the input).
		 */
		function onClear() {
			menuItems.value = initialMenuItems;
		}

		return {
			statusMessage,
			selection,
			menuItems,
			menuConfig,
			formLabel,
			shouldShowBaseLangField,
			onInput,
			onChange,
			onUpdateSelected,
			onClear
		};
	}
} );
</script>
