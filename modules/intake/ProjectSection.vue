<template>
	<section class="wishlist-intake-project">
		<cdx-field
			:disabled="disabled"
			:status="status"
			:messages="messages"
		>
			<cdx-label>
				{{ $i18n( 'communityrequests-project-intro' ).text() }}
				<template #description>
					{{ $i18n( 'communityrequests-project-help' ).text() }}
				</template>
			</cdx-label>
			<cdx-checkbox
				:indeterminate="projects.length > 0 && !allProjects"
				:model-value="allProjects"
				@update:model-value="onUpdateAllProjects"
			>
				{{ $i18n( 'communityrequests-project-all-projects' ).text() }}
			</cdx-checkbox>
			<div role="group" class="cdx-docs-card-group-with-thumbnails">
				<cdx-card
					v-for="project in getProjectList( expanded )"
					:key="project.value"
					class="cdx-docs-card-group-with-thumbnails__card"
					:thumbnail="project.thumbnail"
					@click="onUpdateProject( !isSelectedProject( project.value ), project.value )"
				>
					<template #title>
						<span :aria-label="`project-${ project.value }`">{{ project.label }}</span>
						<cdx-checkbox
							:key="'project-' + project.value"
							:model-value="isSelectedProject( project.value )"
							:input-value="project.value"
							:aria-labelledby="'project-' + project.value"
							@click="onClickProjectCheckbox( $event, project.value )"
						>
						</cdx-checkbox>
					</template>
					<template #description>
						{{ project.url }}
					</template>
				</cdx-card>
			</div>
			<div v-if="expanded" class="wishlist-intake-project-other">
				<cdx-field>
					<cdx-text-input
						:model-value="otherproject"
						:aria-label="$i18n( 'communityrequests-project-other-label' ).text()"
						@input="$emit( 'update:otherproject', $event.target.value )"
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
				class="wishlist-intake-project-toggle"
				action="progressive"
				type="button"
				@click="expanded = !expanded">
				<cdx-icon :icon="expanded ? cdxIconCollapse : cdxIconExpand"></cdx-icon>
				{{ expanded ?
					$i18n( 'communityrequests-project-show-less' ).text() :
					$i18n( 'communityrequests-project-show-all' ).text()
				}}
			</cdx-button>
		</cdx-field>
	</section>
</template>

<script>
const { defineComponent } = require( 'vue' );
const {
	CdxButton,
	CdxCard,
	CdxCheckbox,
	CdxField,
	CdxIcon,
	CdxLabel,
	CdxTextInput
} = require( '@wikimedia/codex' );
const { cdxIconExpand, cdxIconCollapse } = require( './icons.json' );

module.exports = exports = defineComponent( {
	name: 'ProjectSection',
	components: {
		CdxButton,
		CdxCard,
		CdxCheckbox,
		CdxField,
		CdxIcon,
		CdxLabel,
		CdxTextInput
	},
	props: {
		projects: { type: Array, default: () => [] },
		otherproject: { type: String, default: '' },
		disabled: { type: Boolean, default: false },
		status: { type: String, default: 'default' },
		statustype: { type: String, default: 'default' }
	},
	emits: [
		'update:projects',
		'update:otherproject'
	],
	setup() {
		return {
			cdxIconExpand,
			cdxIconCollapse
		};
	},
	data() {
		return {
			/**
			 * Whether the extended project list is shown.
			 *
			 * @type {boolean}
			 */
			expanded: this.shouldBeExpanded(),
			/**
			 * Error messages to display.
			 *
			 * @see https://doc.wikimedia.org/codex/latest/components/demos/field.html#with-validation-messages
			 * @type {Object}
			 */
			messages: {}
		};
	},
	computed: {
		/**
		 * Whether the "All projects" checkbox is ticked.
		 *
		 * @return {boolean}
		 */
		allProjects() {
			return this.projects.length === 1 && this.projects[ 0 ] === 'all';
		}
	},
	methods: {
		/**
		 * Check if a project is selected.
		 *
		 * @param {string} project
		 * @return {boolean}
		 */
		isSelectedProject( project ) {
			return this.allProjects || this.projects.includes( project );
		},
		/**
		 * Handler for (de-)selecting individual projects.
		 *
		 * @param {boolean} selected
		 * @param {string} project
		 */
		onUpdateProject( selected, project ) {
			const projectList = this.getProjectList();
			// Get the full list of projects IDs.
			let currentProjects = this.allProjects ?
				projectList.map( ( p ) => p.value ) :
				this.projects;
			// If we're adding a project, and it isn't already in the list, add it.
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
				// eslint-disable-next-line arrow-body-style
				currentProjects = currentProjects.filter( ( p ) => {
					return projectList.some( ( p2 ) => p2.value === p );
				} );
			}
			// Bubble up the selected projects to WishlistIntake.
			this.$emit( 'update:projects', currentProjects );
		},
		/**
		 * Handler for clicking the project checkbox.
		 *
		 * @param {MouseEvent} event
		 * @param {string} project
		 */
		onClickProjectCheckbox( event, project ) {
			// Prevent the card from being clicked when the checkbox is clicked.
			event.stopPropagation();
			this.onUpdateProject( !this.isSelectedProject( project ), project );
		},
		/**
		 * Handler for (de-)selecting all projects.
		 *
		 * @param {boolean} selectAll
		 */
		onUpdateAllProjects( selectAll ) {
			// Auto-expand when selecting all projects, otherwise keep the current state.
			this.expanded = selectAll ? true : this.expanded;
			this.$emit( 'update:projects', selectAll ? [ 'all' ] : [] );
		},
		/**
		 * Card data for the top projects.
		 *
		 * @see https://doc.wikimedia.org/codex/latest/components/demos/card.html
		 * @return {Array<Object>}
		 */
		getTopProjects() {
			return [
				{
					// NOTE: values are mapped to localized strings in Module:Community_Wishlist.
					value: 'wikipedia',
					url: 'www.wikipedia.org',
					label: mw.msg( 'project-localized-name-group-wikipedia' ),
					thumbnail: {
						width: 200,
						height: 150,
						url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/80/Wikipedia-logo-v2.svg/263px-Wikipedia-logo-v2.svg.png'
					}
				},
				{
					value: 'wikidata',
					url: 'www.wikidata.org',
					label: mw.msg( 'project-localized-name-wikidatawiki' ),
					thumbnail: {
						width: 200,
						height: 150,
						url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/ff/Wikidata-logo.svg/200px-Wikidata-logo.svg.png'
					}
				},
				{
					value: 'commons',
					url: 'commons.wikimedia.org',
					label: mw.msg( 'project-localized-name-commonswiki' ),
					thumbnail: {
						width: 200,
						height: 150,
						url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4a/Commons-logo.svg/200px-Commons-logo.svg.png'
					}
				},
				{
					value: 'wikisource',
					url: 'www.wikisource.org',
					label: mw.msg( 'project-localized-name-group-wikisource' ),
					thumbnail: {
						width: 200,
						height: 150,
						url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4c/Wikisource-logo.svg/200px-Wikisource-logo.svg.png'
					}
				}
			];
		},
		/**
		 * Card data for the extended projects.
		 *
		 * @return {Array<Object>}
		 */
		getExtendedProjects() {
			return [
				{
					value: 'wiktionary',
					url: 'www.wiktionary.org',
					label: mw.msg( 'project-localized-name-group-wiktionary' ),
					thumbnail: {
						width: 200,
						height: 150,
						url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/ec/Wiktionary-logo.svg/200px-Wiktionary-logo.svg.png'
					}
				},
				{
					value: 'wikivoyage',
					url: 'www.wikivoyage.org',
					label: mw.msg( 'project-localized-name-group-wikivoyage' ),
					thumbnail: {
						width: 200,
						height: 150,
						url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Wikivoyage-Logo-v3-icon.svg/200px-Wikivoyage-Logo-v3-icon.svg.png'
					}
				},
				{
					value: 'wikiquote',
					url: 'www.wikiquote.org',
					label: mw.msg( 'project-localized-name-group-wikiquote' ),
					thumbnail: {
						width: 200,
						height: 150,
						url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/fa/Wikiquote-logo.svg/200px-Wikiquote-logo.svg.png'
					}
				},
				{
					value: 'wikiversity',
					url: 'www.wikiversity.org',
					label: mw.msg( 'project-localized-name-group-wikiversity' ),
					thumbnail: {
						width: 200,
						height: 150,
						url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0b/Wikiversity_logo_2017.svg/200px-Wikiversity_logo_2017.svg.png'
					}
				},
				{
					value: 'wikifunctions',
					url: 'www.wikifunctions.org',
					label: mw.msg( 'project-localized-name-wikifunctionswiki' ),
					thumbnail: {
						width: 200,
						height: 150,
						url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Wikifunctions-logo.svg/200px-Wikifunctions-logo.svg.png'
					}
				},
				{
					value: 'wikispecies',
					url: 'www.wikispecies.org',
					label: mw.msg( 'project-localized-name-specieswiki' ),
					thumbnail: {
						width: 200,
						height: 150,
						url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/df/Wikispecies-logo.svg/200px-Wikispecies-logo.svg.png'
					}
				},
				{
					value: 'wikinews',
					url: 'www.wikinews.org',
					label: mw.msg( 'project-localized-name-group-wikinews' ),
					thumbnail: {
						width: 200,
						height: 150,
						url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/24/Wikinews-logo.svg/200px-Wikinews-logo.svg.png'
					}
				},
				{
					value: 'metawiki',
					url: 'meta.wikimedia.org',
					label: mw.msg( 'project-localized-name-metawiki' ),
					thumbnail: {
						width: 200,
						height: 150,
						url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/75/Wikimedia_Community_Logo.svg/200px-Wikimedia_Community_Logo.svg.png'
					}
				},
				{
					value: 'wmcs',
					url: 'wikitech.wikimedia.org',
					label: mw.msg( 'wikimedia-otherprojects-cloudservices' ),
					thumbnail: {
						width: 200,
						height: 150,
						url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3d/Wikimedia_Cloud_Services_logo.svg/200px-Wikimedia_Cloud_Services_logo.svg.png'
					}
				}
			];
		},
		/**
		 * Get a list of projects to display.
		 *
		 * @param {boolean} [expanded=true] Whether to show all projects.
		 * @return {Array<Object>}
		 */
		getProjectList( expanded = true ) {
			if ( expanded ) {
				return this.getTopProjects().concat( this.getExtendedProjects() );
			}

			return this.getTopProjects();
		},
		/**
		 * Whether the projects list should be expanded.
		 * Note this intentionally is false when this.projects is `['all']`,
		 * unless 'otherproject' is not empty. The idea being we only auto-expand
		 * the projects list on initial load if there are projects selected that
		 * are not in the top projects list.
		 *
		 * @return {boolean}
		 */
		shouldBeExpanded() {
			// eslint-disable-next-line arrow-body-style
			return this.projects.some( ( project ) => {
				return this.getExtendedProjects()
					.some( ( p ) => p.value === project );
			} ) || this.otherproject.trim() !== '';
		}
	},
	watch: {
		statustype: {
			handler( newStatus ) {
				if ( newStatus === 'noSelection' ) {
					const otherLabel = mw.msg( 'communityrequests-project-other-label' );
					this.messages = {
						error: mw.msg( 'communityrequests-project-no-selection', 1, otherLabel )
					};
				} else if ( newStatus === 'invalidOther' ) {
					this.messages = {
						error: mw.msg( 'communityrequests-project-other-error', 3 )
					};
				} else {
					this.messages = {};
				}
			}
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.wishlist-intake-project {
	.wishlist-intake-project-other .cdx-field {
		margin-top: @spacing-75;
	}

	.cdx-card .cdx-checkbox {
		position: absolute;
		top: @spacing-75;
		right: @spacing-75;
	}
}

[ dir='rtl' ] .wishlist-intake-project .cdx-card .cdx-checkbox {
	left: @spacing-75;
	right: auto;
}

.wishlist-intake-project-toggle {
	margin-top: @spacing-100;
}

.wishlist-intake-project-other {
	margin-top: @spacing-100;
}

.cdx-docs-card-group-with-thumbnails {
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
	.cdx-docs-card-group-with-thumbnails {
		grid-template-columns: none;
	}
}
</style>
