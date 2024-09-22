<template>
	<form @submit.prevent="handleSubmit">
		<status-section
			v-if="isStaff"
			v-model:status="wish.status"
			:disabled="formDisabled"
			@update:status="updateField( 'status', $event )"
		></status-section>
		<description-section
			v-model:title="wish.title"
			v-model:description="wish.description"
			:titlestatus="titleStatus"
			:descriptionstatus="descriptionStatus"
			:disabled="formDisabled"
			@update:title="updateTitle"
			@update:description="updateField( 'description', $event )"
			@update:pre-submit-promise="addPreSubmitFn"
		></description-section>
		<wish-type-section
			v-model:type="wish.type"
			:status="typeStatus"
			:disabled="formDisabled"
			@update:type="updateField( 'type', $event )">
		</wish-type-section>
		<project-section
			v-model:projects="wish.projects"
			v-model:otherproject="wish.otherproject"
			:projects="projects"
			:otherproject="otherproject"
			:disabled="formDisabled"
			:status="projectStatus"
			:statustype="projectStatusType"
			@update:projects="updateField( 'projects', $event )"
			@update:otherproject="updateField( 'otherproject', $event )">
		</project-section>
		<audience-section
			v-model:audience="wish.audience"
			:status="audienceStatus"
			:disabled="formDisabled"
			@update:audience="updateField( 'audience', $event )"
		></audience-section>
		<phabricator-tasks
			v-model:tasks="wish.tasks"
			:disabled="formDisabled"
			@update:tasks="updateField( 'tasks', $event )"
		></phabricator-tasks>

		<section class="wishlist-intake-form-footer">
			<hr>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<p v-html="$i18n( 'wikimedia-copyrightwarning' ).parse()"></p>
			<cdx-button
				weight="primary"
				action="progressive"
				:disabled="formDisabled"
				class="wishlist-intake-submit"
				@click="handleSubmit"
			>
				<span v-if="exists">{{ $i18n( 'communityrequests-save' ).text() }}</span>
				<span v-else>{{ $i18n( 'communityrequests-publish' ).text() }}</span>
			</cdx-button>
			<a
				:href="returnto"
				class="cdx-button cdx-button--fake-button--enabled
				cdx-button--weight-quiet wishlist-intake-cancel"
			>
				{{ $i18n( 'cancel' ).text() }}
			</a>
			<cdx-message
				v-if="formError"
				type="error"
				class="wishlist-intake-form-error"
			>
				<!-- eslint-disable-next-line vue/no-v-html -->
				<p><strong v-html="formErrorMsg"></strong></p>
				<!-- eslint-disable-next-line vue/no-v-html -->
				<div v-html="formError"></div>
			</cdx-message>
		</section>
	</form>
</template>

<script>
const { defineComponent, reactive } = require( 'vue' );
const { CdxButton, CdxMessage } = require( '@wikimedia/codex' );
const config = require( '../common/config.json' );
const Wish = require( '../common/Wish.js' );
const Util = require( '../common/Util.js' );
const StatusSection = require( './StatusSection.vue' );
const WishTypeSection = require( './WishTypeSection.vue' );
const ProjectSection = require( './ProjectSection.vue' );
const DescriptionSection = require( './DescriptionSection.vue' );
const AudienceSection = require( './AudienceSection.vue' );
const PhabricatorTasks = require( './PhabricatorTasks.vue' );

const api = new mw.Api();

// Functions returning Promises that must be resolved before the form can be validated/submitted.
// This is outside the Vue component as it does not need to be reactive.
const preSubmitFns = [];

module.exports = exports = defineComponent( {
	name: 'SpecialWishlistIntake',
	components: {
		AudienceSection,
		CdxButton,
		CdxMessage,
		DescriptionSection,
		PhabricatorTasks,
		ProjectSection,
		StatusSection,
		WishTypeSection
	},
	props: {
		audience: { type: String, default: '' },
		baselang: { type: String, default: mw.config.get( 'wgUserLanguage' ) },
		created: { type: String, default: '~~~~~' },
		description: { type: String, default: '' },
		otherproject: { type: String, default: '' },
		projects: { type: Array, default: () => [] },
		proposer: { type: String, default: '~~~' },
		status: { type: String, default: Wish.STATUS_SUBMITTED },
		tasks: { type: Array, default: () => [] },
		title: { type: String, default: '' },
		type: { type: String, default: null },
		area: { type: String, default: '' },
		// These are from the initial fetch of wish content,
		// and are later used for edit conflict detection on form submission.
		basetimestamp: { type: String, default: '' },
		curtimestamp: { type: String, default: '' }
	},
	setup( props ) {
		// Customize the subtitle.
		document.querySelector( '#mw-content-subtitle' ).textContent = mw.msg( 'communityrequests-form-subtitle' );

		// Reactive state for the form fields.
		// This should map directly to properties of the Wish class.
		const wish = reactive( {
			audience: props.audience,
			baselang: props.baselang,
			created: props.created,
			description: props.description,
			otherproject: props.otherproject,
			projects: props.projects,
			proposer: props.proposer,
			status: props.status,
			tasks: props.tasks,
			title: props.title,
			type: props.type,
			area: props.area
		} );

		return { wish };
	},
	data() {
		return {
			exists: false,
			pagetitle: '',
			typeStatus: 'default',
			projectStatus: 'default',
			projectStatusType: 'default',
			titleStatus: 'default',
			descriptionStatus: 'default',
			audienceStatus: 'default',
			/**
			 * Whether the form has been changed since it was loaded.
			 *
			 * @type {boolean}
			 */
			formChanged: false,
			/**
			 * Disabled state of the form fields and submit button.
			 *
			 * @type {boolean}
			 */
			formDisabled: false,
			/**
			 * Error state of the form. Either false (no error), true (generic error),
			 * or a string with a specific error message.
			 *
			 * @type {boolean|string}
			 */
			formError: false,
			/**
			 * Whether the form has been submitted.
			 *
			 * @type {boolean}
			 */
			formSubmitted: false,
			/**
			 * @type {Object}
			 */
			allowCloseWindow: mw.confirmCloseWindow(),
			/**
			 * URL to return to after the form is submitted.
			 *
			 * @type {string}
			 */
			returnto: mw.util.getUrl( config.CommunityRequestsHomepage )
		};
	},
	computed: {
		isStaff: Util.isStaff,
		formErrorMsg: () => mw.message(
			'communityrequests-form-error',
			config.CommunityRequestsHomepage
		).parse()
	},
	methods: {
		updateField( field, value ) {
			if ( this.wish[ field ] !== value ) {
				this.wish[ field ] = value;
				this.formChanged = true;
			}
		},
		updateTitle( title ) {
			this.updateField( 'title', title );
			if ( title && !this.exists ) {
				// FIXME: this code may not be needed anymore?
				// If this is a new proposal, keep the new page title in sync with the title field.
				// Existing proposals can't change their page title via the form (but can change the
				// title field).
				title = mw.Title.newFromUserInput( this.wish.title.replaceAll( '/', '_' ) );
				this.pagetitle = Util.getWishPageTitleFromSlug( title.getMainText() );
			}
			// VisualEditor and other scripts rely on this being the correct post-save page name.
			// If the wish title is blank, we use the current page name as a fallback.
			mw.config.set(
				'wgRelevantPageName',
				!this.wish.title ? Util.getPageName() : this.pagetitle
			);
		},
		/**
		 * Validate the form fields.
		 *
		 * @return {boolean} true if the form is valid, false otherwise.
		 */
		validateForm() {
			this.formError = false;
			// Remove translate tags before checking title length.
			const title = this.wish.title
				.replaceAll( /<\/?translate>/g, '' )
				.replaceAll( /<!--T:[0-9]+-->/g, '' );
			this.titleStatus = ( title.length < 5 || title.length > 100 ) ? 'error' : 'default';
			this.descriptionStatus = ( this.wish.description.length < 50 ) ? 'error' : 'default';
			this.typeStatus = this.wish.type === null ? 'error' : 'default';
			// No project selected, other project is empty
			if ( this.wish.projects.length === 0 && !this.wish.otherproject ) {
				this.projectStatus = 'error';
				this.projectStatusType = 'noSelection';
				// Other project has content > 3, but no other project is entered
			} else if ( this.wish.otherproject.length < 3 && this.wish.projects.length < 1 ) {
				this.projectStatus = 'error';
				this.projectStatusType = 'invalidOther';
			} else {
				this.projectStatus = 'default';
				this.projectStatusType = 'default';
			}
			this.audienceStatus = ( this.wish.audience.length < 5 || this.wish.audience.length > 300 ) ? 'error' : 'default';
			return this.typeStatus !== 'error' &&
				this.titleStatus !== 'error' &&
				this.descriptionStatus !== 'error' &&
				this.audienceStatus !== 'error' &&
				this.projectStatus !== 'error';
		},
		/**
		 * Add a function to be called before the form is submitted.
		 * The function is expected to return a Promise, which is
		 * guaranteed to be resolved before the form is submitted.
		 *
		 * @param {Function<Promise|jQuery.Promise>} fn
		 */
		addPreSubmitFn( fn ) {
			preSubmitFns.push( fn );
		},
		/**
		 * Get a unique page title by appending incremental numbers to the end of the title.
		 *
		 * @param {string} pageTitle
		 * @param {number} [pageCounter]
		 * @return {jQuery.Promise<string>|Promise<string>}
		 */
		getUniquePageTitle( pageTitle, pageCounter = 1 ) {
			// A wish being edited is always going to already have a unique title.
			if ( Util.isWishEdit() ) {
				return Promise.resolve( pageTitle );
			}
			// Otherwise, see if it exists and start appending numbers.
			const newTitle = pageTitle + ( pageCounter > 1 ? ' ' + pageCounter : '' );
			return this.pageExists( newTitle ).then( ( exists ) => {
				if ( exists ) {
					return this.getUniquePageTitle( pageTitle, pageCounter + 1 );
				} else {
					return Promise.resolve( newTitle );
				}
			} );
		},
		/**
		 * Get a promise for saving the wish page.
		 *
		 * @param {string} wikitext
		 * @return {jQuery.Promise}
		 */
		getEditPromise( wikitext ) {
			const params = api.assertCurrentUser( {
				action: 'edit',
				title: this.pagetitle,
				text: wikitext,
				formatversion: 2,
				// Tag the edit to track usage of the form.
				tags: [ 'community-wishlist' ],
				// Protect against conflicts
				basetimestamp: this.basetimestamp,
				starttimestamp: this.curtimestamp,
				// Localize errors
				uselang: mw.config.get( 'wgUserLanguage' ),
				errorformat: 'html',
				errorlang: mw.config.get( 'wgUserLanguage' ),
				errorsuselocal: true
			} );
			if ( Util.isNewWish() ) {
				params.createonly = true;
			} else {
				params.nocreate = true;
			}
			return api.postWithEditToken( params );
		},
		/**
		 * Handle form submission.
		 */
		handleSubmit() {
			this.formDisabled = true;
			Promise.all( preSubmitFns.map( ( p ) => p() ) ).then( () => {
				// @todo Handle this nicer?
				if ( !this.validateForm() ) {
					this.formDisabled = false;
					return;
				}

				// Save the wish page, first checking for duplicate titles.
				this.getUniquePageTitle( this.pagetitle ).then( ( uniqueTitle ) => {
					this.pagetitle = uniqueTitle;
					const wikitext = Util.getWishTemplate().getWikitext( new Wish( this.wish ) );
					this.getEditPromise( wikitext ).then( ( saveResult ) => {
						if ( saveResult.edit && !saveResult.edit.nochange ) {
							// Replicate what is done in postEdit's fireHookOnPageReload() function,
							// but for a different page.
							mw.storage.session.set(
								// Same storage key structure as in MediaWiki's
								// resources/src/mediawiki.action/mediawiki.action.view.postEdit.js
								'mw-PostEdit' + this.pagetitle.replaceAll( ' ', '_' ),
								Util.isWishEdit() ? 'saved' : 'created',
								// Same duration as EditPage::POST_EDIT_COOKIE_DURATION.
								1200
							);
						}
						this.formSubmitted = true; // @todo Unused variable?
						// Allow the window to close/navigate after submission was successful.
						this.allowCloseWindow.release();
						window.location.replace( mw.util.getUrl( this.pagetitle ) );
					} ).fail( this.handleError.bind( this ) );
				} );
			} ).catch( this.handleError.bind( this ) );
		},
		/**
		 * Handle an error from the API.
		 *
		 * @param {Error} errObj
		 * @param {Object} error Response from the API
		 * @param {string} error.info
		 */
		handleError( errObj, error ) {
			Util.logError( 'edit failed', errObj );
			this.formError = api.getErrorMessage( error ).html();
			this.formDisabled = false;
		},
		/**
		 * Check if a page exists.
		 *
		 * @param {string} title
		 * @return {Promise<boolean>}
		 */
		pageExists( title ) {
			return api.get( {
				action: 'query',
				format: 'json',
				titles: title
			} ).then( ( res ) => Promise.resolve( res.query.pages[ -1 ] === undefined ) );
		}
	},
	beforeMount() {
		this.pagetitle = Util.getWishPageTitleFromSlug( Util.getWishSlug() );
		if ( Util.isNewWish() ) {
			this.exists = false;
		} else {
			this.returnto = mw.util.getUrl( this.pagetitle );
			this.exists = true;
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.wishlist-intake-container {
	.cdx-field,
	section:last-child,
	.wishlist-intake-form-error {
		margin-top: @spacing-200;
	}
}

.wishlist-intake-form-footer .cdx-checkbox {
	margin: @spacing-100 auto;
}

.wishlist-intake-cancel {
	margin-left: @spacing-75;
}

[ dir='rtl' ] .wishlist-intake-cancel {
	margin-left: 0;
	margin-right: @spacing-75;
}
</style>
