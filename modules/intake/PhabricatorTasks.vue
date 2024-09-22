<template>
	<section class="wishlist-intake-tasks">
		<cdx-field :disabled="disabled">
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
		</cdx-field>
	</section>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxField, CdxChipInput } = require( '@wikimedia/codex' );

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
		tasks: { type: Array, default: () => [] },
		disabled: { type: Boolean, default: false }
	},
	emits: [
		'update:tasks'
	],
	data( props ) {
		return {
			// An array of ChipInputItems containing Phabricator IDs.
			taskList: this.arrayToChipItems( props.tasks )
		};
	},
	methods: {
		/**
		 * Uppercase and sort an array of task IDs.
		 *
		 * @param {Array<string>} taskIds
		 * @return {Array<string>}
		 */
		normalizeTaskIds( taskIds ) {
			const allTaskIds = Array.prototype.concat( ...taskIds.map( ( taskId ) => {
				// One taskId might actually contain multiple,
				// e.g. if the user doesn't put a space between them.
				const currentTaskIds = taskId.match( /[Tt][0-9]+/g ) || [];
				return currentTaskIds.map( ( t ) => t.toUpperCase() );
			} ) );
			// Filter to be unique, and sort.
			return allTaskIds.filter( ( v, i, a ) => a.indexOf( v ) === i ).sort();
		},
		updateInputChips( chips ) {
			const taskIds = this.normalizeTaskIds( chips.map( ( c ) => c.value ) );
			this.$emit( 'update:tasks', taskIds );
		},
		arrayToChipItems( array ) {
			return array.map( ( t ) => ( { value: t } ) );
		}
	},
	watch: {
		tasks: {
			handler( newVal ) {
				this.taskList = this.arrayToChipItems( newVal );
			},
			deep: true
		}
	}
} );
</script>
