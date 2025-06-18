<template>
	<cdx-field
		class="ext-communityrequests-intake__fieldset"
		:is-fieldset="true"
		:disabled="formDisabled"
		@change="formChanged = true"
	>
		<status-section
			v-if="isStaff"
			v-model:status="wish.status"
			@update:status="formChanged = true"
		></status-section>
		<input
			:value="status"
			type="hidden"
			name="status">
		<description-section
			v-model:title="wish.title"
			v-model:description="wish.description"
			:title-status="titleStatus"
			:description-status="descriptionStatus"
			@update:description="formChanged = true"
			@update:pre-submit-promise="addPreSubmitFn"
		></description-section>
		<wish-type-section
			:type="wish.type"
			:status="typeStatus"
			@update:type="$event => ( wish.type = $event )"
		></wish-type-section>
		<project-section
			v-model:projects="wish.projects"
			v-model:other-project="wish.otherProject"
			:status="projectStatus"
			:status-type="projectStatusType"
		></project-section>
		<audience-section
			v-model:audience="wish.audience"
			:status="audienceStatus"
		></audience-section>
		<phabricator-tasks
			v-model:tasks="wish.phabTasks"
		></phabricator-tasks>
		<input
			:value="created"
			type="hidden"
			name="created"
		>
		<input
			:value="proposer"
			type="hidden"
			name="proposer"
		>
		<input
			:value="baseRevId"
			type="hidden"
			name="baserevid"
		>

		<footer class="ext-communityrequests-intake__footer">
			<hr>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<p v-html="$i18n( 'wikimedia-copyrightwarning' ).parse()"></p>
			<cdx-button
				weight="primary"
				action="progressive"
				class="ext-communityrequests-intake__submit"
				type="submit"
				@click="handleSubmit"
			>
				<span v-if="exists">{{ $i18n( 'communityrequests-save' ).text() }}</span>
				<span v-else>{{ $i18n( 'communityrequests-publish' ).text() }}</span>
			</cdx-button>
			<a
				:href="returnTo"
				class="cdx-button cdx-button--fake-button--enabled
			cdx-button--weight-quiet ext-communityrequests-intake__cancel"
			>
				{{ $i18n( 'cancel' ).text() }}
			</a>
			<cdx-message
				v-if="formError"
				type="error"
				class="ext-communityrequests-intake__form-error"
			>
				<!-- eslint-disable-next-line vue/no-v-html -->
				<p><strong v-html="formErrorMsg"></strong></p>
				<!-- eslint-disable-next-line vue/no-v-html -->
				<div v-html="formError"></div>
			</cdx-message>
		</footer>
	</cdx-field>
</template>

<script>
/* eslint-disable vue/no-unused-properties */
const { computed, defineComponent, nextTick, onMounted, reactive, ref, ComputedRef, Ref } = require( 'vue' );
const { CdxButton, CdxField, CdxMessage } = require( '../codex.js' );
const { CommunityRequestsHomepage, CommunityRequestsStatuses } = require( '../common/config.json' );
const Util = require( '../common/Util.js' );
const StatusSection = require( './StatusSection.vue' );
const WishTypeSection = require( './WishTypeSection.vue' );
const ProjectSection = require( './ProjectSection.vue' );
const DescriptionSection = require( './DescriptionSection.vue' );
const AudienceSection = require( './AudienceSection.vue' );
const PhabricatorTasks = require( './PhabricatorTasks.vue' );

const api = new mw.Api();
const defaultStatusKey = Object.keys( CommunityRequestsStatuses )
	.find( ( key ) => CommunityRequestsStatuses[ key ].default );

// Functions returning Promises that must be resolved before the form can be validated/submitted.
// This is outside the Vue component as it does not need to be reactive.
const preSubmitFns = [];

module.exports = exports = defineComponent( {
	name: 'SpecialWishlistIntake',
	components: {
		AudienceSection,
		CdxButton,
		CdxField,
		CdxMessage,
		DescriptionSection,
		PhabricatorTasks,
		ProjectSection,
		StatusSection,
		WishTypeSection
	},
	props: {
		audience: { type: String, default: '' },
		baseRevId: { type: Number, default: 0 },
		created: { type: String, default: '' },
		description: { type: String, default: '' },
		otherProject: { type: String, default: '' },
		phabTasks: { type: Array, default: () => [] },
		projects: { type: Array, default: () => [] },
		proposer: { type: String, default: mw.config.get( 'wgUserName' ) },
		status: {
			type: String,
			default: defaultStatusKey || Object.keys( CommunityRequestsStatuses )[ 0 ]
		},
		title: { type: String, default: '' },
		type: { type: String, default: '' }
	},
	setup( props ) {
		// Reactive properties

		/**
		 * Reactive object representing the wish being edited or created.
		 *
		 * @type {Ref<Object>}
		 */
		const wish = reactive( Object.assign( {}, props ) );
		/**
		 * Status of the type field.
		 *
		 * @type {Ref<string>}
		 */
		const typeStatus = ref( 'default' );
		/**
		 * Status of the project field.
		 *
		 * @type {Ref<string>}
		 */
		const projectStatus = ref( 'default' );
		/**
		 * The type of error status for the project field.
		 *
		 * @type {Ref<string>}
		 */
		const projectStatusType = ref( 'default' );
		/**
		 * Status of the title field.
		 *
		 * @type {Ref<string>}
		 */
		const titleStatus = ref( 'default' );
		/**
		 * Status of the description field.
		 *
		 * @type {Ref<string>}
		 */
		const descriptionStatus = ref( 'default' );
		/**
		 * Status of the audience field.
		 *
		 * @type {Ref<string>}
		 */
		const audienceStatus = ref( 'default' );
		/**
		 * Whether the form has been changed since it was loaded.
		 *
		 * @type {Ref<boolean>}
		 */
		const formChanged = ref( false );
		/**
		 * Disabled state of the form fields and submit button.
		 *
		 * @type {Ref<boolean>}
		 */
		const formDisabled = ref( false );
		/**
		 * Error state of the form. Either false (no error), true (generic error),
		 * or a string with a specific error message.
		 *
		 * @type {Ref<boolean|string>}
		 */
		const formError = ref( false );

		// Computed properties

		/**
		 * Error message to display when the form has an error.
		 *
		 * @type {ComputedRef<string>}
		 */
		const formErrorMsg = computed( () => mw.message(
			'communityrequests-form-error',
			`Talk:${ CommunityRequestsHomepage }`
		).parse() );

		// Non-reactive properties

		/**
		 * Whether the user is a staff member.
		 *
		 * @todo replace with proper user group
		 * @type {boolean}
		 */
		const isStaff = Util.isStaff();
		/**
		 * Whether the wish already exists (is being edited) or is a new wish.
		 *
		 * @type {boolean}
		 */
		const exists = Util.isWishEdit();
		/**
		 * URL to return to after the form is submitted.
		 *
		 * @type {string}
		 */
		const returnTo = mw.util.getUrl( exists ?
			Util.getWishPageTitleFromId( mw.config.get( 'intakeWishId' ) ) :
			CommunityRequestsHomepage
		);
		/**
		 * The <form> element.
		 *
		 * @type {HTMLFormElement}
		 */
		let form;

		// Functions

		/**
		 * Validate the form fields.
		 *
		 * @return {boolean} true if the form is valid, false otherwise.
		 */
		function validateForm() {
			formError.value = false;
			// Remove translate tags before checking title length.
			const title = wish.title
				.replace( /<\/?translate>/g, '' )
				.replace( /<!--T:[0-9]+-->/g, '' );
			titleStatus.value = ( title.length < 5 || title.length > 100 ) ? 'error' : 'default';
			descriptionStatus.value = ( wish.description.length < 50 ) ? 'error' : 'default';
			typeStatus.value = wish.type === '' ? 'error' : 'default';
			// No project selected, other project is empty
			if ( wish.projects.length === 0 && !wish.otherProject ) {
				projectStatus.value = 'error';
				projectStatusType.value = 'noSelection';
				// Other project has content > 3, but no other project is entered
			} else if ( wish.otherProject.length < 3 && wish.projects.length < 1 ) {
				projectStatus.value = 'error';
				projectStatusType.value = 'invalidOther';
			} else {
				projectStatus.value = 'default';
				projectStatusType.value = 'default';
			}
			audienceStatus.value = ( wish.audience.length < 5 || wish.audience.length > 300 ) ? 'error' : 'default';
			return typeStatus.value !== 'error' &&
				titleStatus.value !== 'error' &&
				descriptionStatus.value !== 'error' &&
				audienceStatus.value !== 'error' &&
				projectStatus.value !== 'error';
		}
		/**
		 * Add a function to be called before the form is submitted.
		 * The function is expected to return a Promise, which is
		 * guaranteed to be resolved before the form is submitted.
		 *
		 * @param {Function<Promise|jQuery.Promise>} fn
		 */
		function addPreSubmitFn( fn ) {
			preSubmitFns.push( fn );
		}
		/**
		 * Handle form submission.
		 *
		 * @param {Event} e
		 */
		function handleSubmit( e ) {
			e.preventDefault();
			formDisabled.value = true;
			Promise.all( preSubmitFns.map( ( p ) => p() ) ).then( () => {
				formDisabled.value = false;

				if ( !validateForm() ) {
					return;
				}

				// Allows mw.confirmCloseWindow to release the lock on page unload.
				formChanged.value = false;

				nextTick( () => {
					document.querySelector( '#ext-communityrequests-intake-form' ).submit();
				} );
			} ).catch( handleError.bind( this ) );
		}
		/**
		 * Handle an error from the API.
		 *
		 * @param {Error} errObj
		 * @param {Object} error Response from the API
		 * @param {string} error.info
		 */
		function handleError( errObj, error ) {
			Util.logError( 'edit failed', errObj );
			formError.value = api.getErrorMessage( error ).html();
			formDisabled.value = false;
		}

		// Lifecycle hooks
		onMounted( () => {
			// Prevents the window from being closed or navigated away from if the form is dirty.
			mw.confirmCloseWindow( { test: () => formChanged.value } );

			form = document.querySelector( '#ext-communityrequests-intake-form' );
			form.addEventListener( 'submit', handleSubmit );
		} );

		return {
			wish,
			typeStatus,
			projectStatus,
			projectStatusType,
			titleStatus,
			descriptionStatus,
			audienceStatus,
			formChanged,
			formDisabled,
			formError,
			formErrorMsg,
			isStaff,
			exists,
			returnTo,
			addPreSubmitFn,
			handleSubmit
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

// Force full-width.
.mw-htmlform-codex {
	max-width: unset;
}

.ext-communityrequests-intake {
	.ext-communityrequests-intake__fieldset .cdx-field:not( .ext-communityrequests-intake__status ),
	&__footer,
	&__form-error {
		margin-top: @spacing-200;
	}

	&__cancel {
		margin-left: @spacing-75;

		[ dir='rtl' ] & {
			margin-left: 0;
			margin-right: @spacing-75;
		}
	}

	.cdx-label:not( .cdx-radio__label, .cdx-checkbox__label ) .cdx-label__label__text {
		font-weight: @font-weight-bold;
	}
}
</style>
