<template>
	<cdx-field
		class="ext-communityrequests-intake__tags"
		:is-fieldset="true"
		:optional="true"
	>
		<cdx-multiselect-lookup
			v-model:input-chips="chips"
			v-model:selected="selection"
			v-model:input-value="inputValue"
			:keep-input-on-selection="true"
			:menu-items="menuItems"
			:menu-config="menuConfig"
			:aria-label="$i18n( 'communityrequests-tags-label' ).text()"
			@input="onInput"
			@update:selected="onSelection"
			@blur="validateInstantly"
			@keydown.enter="validateInstantly"
		>
		</cdx-multiselect-lookup>
		<template #label>
			{{ $i18n( 'communityrequests-tags-label' ).text() }}
		</template>
		<template #description>
			{{ $i18n( 'communityrequests-tags-description' ).text() }}
		</template>
		<input
			:value="tags"
			type="hidden"
			name="tags"
		>
	</cdx-field>
</template>

<script>
const { defineComponent, nextTick, ref } = require( 'vue' );
const { CdxField, CdxMultiselectLookup } = require( '../codex.js' );
const { CommunityRequestsTags } = require( '../common/config.json' );
const tagsConfig = CommunityRequestsTags.navigation;
const tagsList = [];
for ( const value in tagsConfig ) {
	if ( Object.prototype.hasOwnProperty.call( tagsConfig, value ) ) {
		// Messages are configurable but by default will include:
		// * communityrequests-tag-admins
		// * communityrequests-tag-bots-gadgets
		// * communityrequests-tag-categories
		// * communityrequests-tag-citations
		// * communityrequests-tag-editing
		// * communityrequests-tag-ios
		// * communityrequests-tag-android
		// * communityrequests-tag-mobile-web
		// * communityrequests-tag-multimedia-commons
		// * communityrequests-tag-newcomers
		// * communityrequests-tag-notifications
		// * communityrequests-tag-patrolling
		// * communityrequests-tag-reading
		// * communityrequests-tag-search
		// * communityrequests-tag-talk-pages
		// * communityrequests-tag-templates
		// * communityrequests-tag-translation
		// * communityrequests-tag-watchlist-rc
		// * communityrequests-tag-wikidata
		// * communityrequests-tag-wikisource
		// * communityrequests-tag-wiktionary
		const label = mw.msg(
			tagsConfig[ value ].label ?
				tagsConfig[ value ].label :
				`communityrequests-tag-${ value }`
		);
		tagsList.push( { value, label } );
	}
}

module.exports = exports = defineComponent( {
	name: 'TagsSection',
	components: {
		CdxField,
		CdxMultiselectLookup
	},
	props: {
		tags: { type: Array, default: () => [] }
	},
	emits: [
		'update:tags'
	],
	setup( props, { emit } ) {
		const chips = ref( tagsList.filter( ( tag ) => props.tags.includes( tag.value ) ) );
		const selection = ref( props.tags );
		const inputValue = ref( '' );
		const menuItems = ref( tagsList );

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
		}

		/**
		 * Handle lookup input.
		 *
		 * @param {string} value The new input value
		 */
		function onInput( value ) {
			// Reset menu items if the input was cleared.
			if ( !value ) {
				menuItems.value = tagsList;
				return;
			}

			// Make sure this data is still relevant first.
			if ( inputValue.value !== value ) {
				return;
			}

			// Update menuItems to tags that match the input value.
			menuItems.value = tagsList.filter(
				( tag ) => tag.label.toLowerCase().includes( value.toLowerCase() )
			);
		}

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
