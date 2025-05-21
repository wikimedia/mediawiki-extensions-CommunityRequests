<template>
	<cdx-field class="ext-communityrequests-intake__tasks">
		<cdx-chip-input
			v-model:input-chips="taskList"
			:chip-aria-description="$i18n( 'communityrequests-phabricator-chip-desc' ).text()"
			@update:input-chips="updateInputChips"
		>
		</cdx-chip-input>
		<template #label>
			{{ $i18n( 'communityrequests-phabricator-label' ).text() }}
		</template>
		<template #description>
			{{ $i18n( 'communityrequests-phabricator-desc' ).text() }}
		</template>
		<!-- eslint-disable-next-line vue/html-self-closing -->
		<input
			:value="normalizeTaskIds( tasks )"
			type="hidden"
			name="phabTasks" />
	</cdx-field>
</template>

<script>
const { defineComponent, ref, watch, Ref } = require( 'vue' );
const { CdxField, CdxChipInput } = require( '../codex.js' );

/**
 * This component accepts and provides a comma-separated wikitext list of
 * Phabricator links, and handles all splitting, combining, and normalization
 * itself.
 */
module.exports = exports = defineComponent( {
	name: 'PhabricatorTasks',
	components: {
		CdxField,
		CdxChipInput
	},
	props: {
		tasks: { type: Array, default: () => [] }
	},
	emits: [
		'update:tasks'
	],
	setup( props, { emit } ) {
		/**
		 * The taskList is a ref that holds the array of ChipInputItems.
		 *
		 * @type {Ref<Array<Object>>}
		 */
		const taskList = ref( arrayToChipItems( props.tasks ) );

		/**
		 * Uppercase and sort an array of task IDs.
		 *
		 * @param {Array<string>} taskIds
		 * @return {Array<string>}
		 */
		function normalizeTaskIds( taskIds ) {
			const allTaskIds = Array.prototype.concat( ...taskIds.map( ( taskId ) => {
				// One taskId might actually contain multiple,
				// e.g. if the user doesn't put a space between them.
				const currentTaskIds = String( taskId ).match( /[Tt][0-9]+/g ) || [];
				return currentTaskIds.map( ( t ) => t.toUpperCase() );
			} ) );
			// Filter to be unique, and sort.
			return allTaskIds.filter( ( v, i, a ) => a.indexOf( v ) === i ).sort();
		}

		/**
		 * Emits an event to update the parent component with the normalized task IDs.
		 *
		 * @param {Array<Object>} chips
		 */
		function updateInputChips( chips ) {
			const taskIds = normalizeTaskIds( chips.map( ( c ) => c.value ) );
			emit( 'update:tasks', taskIds );
		}

		/**
		 * Converts an array of task IDs into an array of ChipInputItems.
		 *
		 * @param {Array<number>} array
		 * @return {Array<Object>}
		 */
		function arrayToChipItems( array ) {
			return array.map( ( t ) => ( { value: t } ) );
		}

		watch( () => props.tasks, ( newVal ) => {
			// Ensure the taskList is updated when the tasks prop changes.
			taskList.value = arrayToChipItems( newVal );
		}, { deep: true } );

		return {
			taskList,
			normalizeTaskIds,
			updateInputChips
		};
	}
} );
</script>
