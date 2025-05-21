<template>
	<cdx-field
		class="ext-communityrequests-intake__project"
		:status="status"
		:messages="messages"
		:is-fieldset="true"
	>
		<template #label>
			{{ $i18n( 'communityrequests-project-intro' ).text() }}
		</template>
		<template #description>
			{{ $i18n( 'communityrequests-project-help' ).text() }}
		</template>
		<cdx-checkbox
			:indeterminate="projects.length > 0 && !allProjects"
			:model-value="allProjects"
			@update:model-value="onUpdateAllProjects"
		>
			{{ $i18n( 'communityrequests-project-all-projects' ).text() }}
		</cdx-checkbox>
		<div role="group" class="ext-communityrequests-intake__project-card">
			<cdx-card
				v-for="project in getProjectList( expanded )"
				:key="'project-' + project.value"
				class="ext-communityrequests-intake__project-card__card"
				:thumbnail="project.thumbnail"
				@click.stop="onUpdateProject( project.value )"
			>
				<template #title>
					<span :aria-label="`project-${ project.value }`">{{ project.label }}</span>
					<cdx-checkbox
						:key="`project-checkbox-${ project.value }`"
						:model-value="isSelectedProject( project.value )"
						:input-value="project.value"
						:aria-labelledby="`project-${ project.value }`"
						@click.stop="onUpdateProject( project.value )"
					>
					</cdx-checkbox>
				</template>
				<template #description>
					{{ project.domain }}
				</template>
			</cdx-card>
		</div>
		<div v-if="expanded" class="ext-communityrequests-intake__project-other">
			<cdx-field>
				<cdx-text-input
					:model-value="otherProject"
					:aria-label="$i18n( 'communityrequests-project-other-label' ).text()"
					name="otherProject"
					@input="$emit( 'update:other-project', $event.target.value )"
				>
				</cdx-text-input>
				<template #label>
					{{ $i18n( 'communityrequests-project-other-label' ).text() }}
				</template>
				<template #description>
					{{ $i18n( 'communityrequests-project-other-description' ).text() }}
				</template>
			</cdx-field>
		</div>
		<cdx-button
			weight="quiet"
			class="ext-communityrequests-intake__project-toggle"
			action="progressive"
			type="button"
			@click="expanded = !expanded">
			<cdx-icon :icon="expanded ? cdxIconCollapse : cdxIconExpand"></cdx-icon>
			{{ expanded ?
				$i18n( 'communityrequests-project-show-less' ).text() :
				$i18n( 'communityrequests-project-show-all' ).text()
			}}
		</cdx-button>
		<!-- eslint-disable-next-line vue/html-self-closing -->
		<input
			:value="projects"
			type="hidden"
			name="projects" />
	</cdx-field>
</template>

<script>
const { computed, defineComponent, ref, ComputedRef, Ref } = require( 'vue' );
const { CdxButton, CdxCard, CdxCheckbox, CdxField, CdxIcon, CdxTextInput } = require( '../codex.js' );
const { cdxIconExpand, cdxIconCollapse } = require( './icons.json' );
const { CommunityRequestsProjects } = require( '../common/config.json' );

module.exports = exports = defineComponent( {
	name: 'ProjectSection',
	components: {
		CdxButton,
		CdxCard,
		CdxCheckbox,
		CdxField,
		CdxIcon,
		CdxTextInput
	},
	props: {
		projects: { type: Array, default: () => [] },
		otherProject: { type: String, default: '' },
		status: { type: String, default: 'default' },
		statusType: { type: String, default: 'default' }
	},
	emits: [
		'update:projects',
		'update:other-project'
	],
	setup( props, { emit } ) {
		/**
		 * Whether the extended project list is shown.
		 *
		 * @type {Ref<boolean>}
		 */
		const expanded = ref( shouldBeExpanded() );
		/**
		 * Error messages to display.
		 *
		 * @see https://doc.wikimedia.org/codex/latest/components/demos/field.html#with-validation-messages
		 * @type {ComputedRef<Object>}
		 */
		const messages = computed( () => {
			if ( props.statusType === 'noSelection' ) {
				const otherLabel = mw.msg( 'communityrequests-project-other-label' );
				return { error: mw.msg( 'communityrequests-project-no-selection', 1, otherLabel ) };
			} else if ( props.statusType === 'invalidOther' ) {
				return { error: mw.msg( 'communityrequests-project-other-error', 3 ) };
			}
			return {};
		} );
		/**
		 * Whether the "All projects" checkbox is ticked.
		 *
		 * @return {ComputedRef<boolean>}
		 */
		const allProjects = computed( () => props.projects.length === 1 && props.projects[ 0 ] === 'all' );

		/**
		 * Check if a project is selected.
		 *
		 * @param {string} project
		 * @return {boolean}
		 */
		function isSelectedProject( project ) {
			return allProjects.value || props.projects.includes( project );
		}

		/**
		 * Handler for (de-)selecting individual projects.
		 *
		 * @param {string} project
		 */
		function onUpdateProject( project ) {
			const selected = !isSelectedProject( project );
			const projectList = getProjectList();
			// Get the full list of projects IDs.
			let currentProjects = allProjects.value ?
				projectList.map( ( p ) => p.value ) :
				props.projects;
			// If we're adding a project, and it isn't yet in the list, add it.
			if ( selected && !currentProjects.includes( project ) ) {
				currentProjects.push( project );
			} else {
				// Otherwise, remove it.
				currentProjects = currentProjects.filter( ( p ) => p !== project );
			}
			// Auto-check "All projects" if all projects are selected.
			const intersection = projectList.filter( ( p ) => currentProjects.includes( p.value ) );
			const willBeAllProjects = intersection.length === projectList.length;
			if ( willBeAllProjects ) {
				currentProjects = [ 'all' ];
			} else {
				// Remove any unknown values (T362275#9912455)
				currentProjects = currentProjects.filter(
					( p ) => projectList.some( ( p2 ) => p2.value === p )
				);
			}
			// Bubble up the selected projects to WishlistIntake.
			emit( 'update:projects', currentProjects );
		}

		/**
		 * Handler for (de-)selecting all projects.
		 *
		 * @param {boolean} selectAll
		 */
		function onUpdateAllProjects( selectAll ) {
			// Auto-expand when selecting all projects, otherwise keep the current state.
			expanded.value = selectAll ? true : expanded.value;
			emit( 'update:projects', selectAll ? [ 'all' ] : [] );
		}

		/**
		 * Card data for the top projects.
		 *
		 * @see https://doc.wikimedia.org/codex/latest/components/demos/card.html
		 * @return {Array<Object>}
		 */
		function getTopProjects() {
			return Object.keys( CommunityRequestsProjects ).slice( 0, 4 ).map( ( key ) => ( {
				value: key,
				domain: CommunityRequestsProjects[ key ].domain,
				// Messages are configurable. By default they include:
				// * project-localized-name-commonswiki
				// * project-localized-name-group-wikipedia
				// * project-localized-name-group-wikisource
				// * project-localized-name-wikidatawiki
				label: mw.msg( CommunityRequestsProjects[ key ].label ),
				thumbnail: {
					width: 200,
					height: 150,
					url: CommunityRequestsProjects[ key ].logo
				}
			} ) );
		}

		/**
		 * Card data for the extended projects.
		 *
		 * @return {Array<Object>}
		 */
		function getExtendedProjects() {
			return Object.keys( CommunityRequestsProjects ).slice( 4 ).map( ( key ) => ( {
				value: key,
				domain: CommunityRequestsProjects[ key ].domain,
				// Messages are configurable. By default they include:
				// * project-localized-name-group-wikinews
				// * project-localized-name-group-wikiquote
				// * project-localized-name-group-wikiversity
				// * project-localized-name-group-wikivoyage
				// * project-localized-name-group-wiktionary
				// * project-localized-name-mediawikiwiki
				// * project-localized-name-metawiki
				// * project-localized-name-specieswiki
				// * project-localized-name-wikidatawiki
				// * project-localized-name-wikifunctionswiki
				// * wikimedia-otherprojects-cloudservices
				label: mw.msg( CommunityRequestsProjects[ key ].label ),
				thumbnail: {
					width: 200,
					height: 150,
					url: CommunityRequestsProjects[ key ].logo
				}
			} ) );
		}

		/**
		 * Get a list of projects to display.
		 *
		 * @param {boolean} [showAllProjects=true] Whether to show all projects.
		 * @return {Array<Object>}
		 */
		function getProjectList( showAllProjects = true ) {
			if ( showAllProjects ) {
				return getTopProjects().concat( getExtendedProjects() );
			}
			return getTopProjects();
		}

		/**
		 * Whether the projects list should be expanded.
		 * Note this intentionally is false when this.projects is `[-1]`,
		 * unless 'otherProject' is not empty. The idea being we only auto-expand
		 * the projects list on initial load if there are projects selected that
		 * are not in the top projects list.
		 *
		 * @return {boolean}
		 */
		function shouldBeExpanded() {
			// eslint-disable-next-line arrow-body-style
			return props.projects.some( ( project ) => {
				return getExtendedProjects()
					.some( ( p ) => p.value === project );
			} ) || props.otherProject.trim() !== '';
		}

		return {
			cdxIconExpand,
			cdxIconCollapse,
			expanded,
			messages,
			allProjects,
			isSelectedProject,
			onUpdateProject,
			onUpdateAllProjects,
			getProjectList
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-communityrequests-intake__project {
	&-other {
		margin-top: @spacing-100;

		.cdx-field {
			margin-top: @spacing-75;
		}
	}

	.cdx-card .cdx-checkbox {
		position: absolute;
		top: @spacing-75;
		right: @spacing-75;
	}

	&-toggle.cdx-button {
		margin-top: @spacing-100;
	}
}

[ dir='rtl' ] .ext-communityrequests-intake__project .cdx-card .cdx-checkbox {
	left: @spacing-75;
	right: auto;
}

.ext-communityrequests-intake__project-card {
	display: grid;
	grid-template-columns: auto auto;
	gap: @spacing-100;

	p {
		margin-top: 100px;
		font-weight: @font-weight-bold;
	}

	&__card {
		cursor: pointer;
		padding: @spacing-100;

		.cdx-card__thumbnail.cdx-thumbnail .cdx-thumbnail__image {
			background-size: contain;
			border: 0;
		}
	}
}

// Mobile overrides. We may later use Codex checkboxes which are
// more well-suited for this purpose.
// stylelint-disable-next-line media-query-no-invalid
@media ( max-width: @max-width-breakpoint-mobile ) {
	.ext-communityrequests-intake__project-card {
		grid-template-columns: none;
	}
}
</style>
