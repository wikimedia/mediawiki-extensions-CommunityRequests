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
			:aria-label="$i18n( 'communityrequests-wishes-filters-statuses-label' ).text()"
			@input="onInput"
			@update:selected="onSelection"
			@blur="validateInstantly"
			@keydown.enter="validateInstantly"
		>
		</cdx-multiselect-lookup>
		<template #label>
			{{ $i18n( 'communityrequests-wishes-filters-statuses-label' ).text() }}
		</template>
		<input
			:value="statuses"
			type="hidden"
			name="statuses"
		>
	</cdx-field>
</template>

<script>
const { defineComponent, nextTick, ref, watch } = require( 'vue' );
const { CdxField, CdxMultiselectLookup } = require( '../codex.js' );
const { CommunityRequestsStatuses } = require( '../common/config.json' );
const statusesList = Object.keys( CommunityRequestsStatuses )
	.map( ( status ) => ( {
		// Messages are configurable. By default, they include:
		// * communityrequests-status-draft
		// * communityrequests-status-submitted
		// * communityrequests-status-open
		// * communityrequests-status-in-progress
		// * communityrequests-status-delivered
		// * communityrequests-status-blocked
		// * communityrequests-status-archived
		label: mw.msg( CommunityRequestsStatuses[ status ].label ),
		value: status
	} ) );

module.exports = exports = defineComponent( {
	name: 'StatusesFilter',
	components: {
		CdxField,
		CdxMultiselectLookup
	},
	props: {
		statuses: { type: Array, default: () => [] },
		clearField: { type: Boolean, default: false }
	},
	emits: [
		'update:statuses'
	],
	setup( props, { emit } ) {
		const chips = ref( statusesList.filter(
			( status ) => props.statuses.includes( status.value )
		) );
		const selection = ref( props.statuses );
		const inputValue = ref( '' );
		const menuItems = ref( statusesList );

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
		 * Emit the updated statuses.
		 */
		function onSelection() {
			if ( selection.value !== null ) {
				emit( 'update:statuses', selection.value );
			}
			// Reset the search value and menu.
			inputValue.value = '';
			menuItems.value = statusesList;
		}

		/**
		 * Handle lookup input.
		 *
		 * @param {string} value The new input value
		 */
		function onInput( value ) {
			// Reset menu items if the input was cleared.
			if ( !value ) {
				menuItems.value = statusesList;
				return;
			}

			// Make sure this data is still relevant first.
			if ( inputValue.value !== value ) {
				return;
			}

			// Update menuItems to statues that match the input value.
			menuItems.value = statusesList.filter(
				( status ) => status.label.toLowerCase().includes( value.toLowerCase() )
			);
		}

		watch( () => props.clearField, ( newValue ) => {
			if ( newValue ) {
				selection.value = [];
				inputValue.value = '';
				emit( 'update:statuses', selection.value );
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
