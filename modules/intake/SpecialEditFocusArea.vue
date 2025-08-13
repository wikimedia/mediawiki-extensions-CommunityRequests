<template>
	<cdx-field
		class="ext-communityrequests-intake__fieldset"
		:is-fieldset="true"
		:disabled="formDisabled"
		@change="formChanged = true"
	>
		<status-section
			v-model:status="focusArea.status"
			@update:status="formChanged = true"
		></status-section>
		<input
			:value="focusArea.status"
			type="hidden"
			name="status">
		<description-section
			v-model:title="focusArea.title"
			v-model:description="focusArea.description"
			:title-status="titleStatus"
			:description-status="descriptionStatus"
			:description-label="$i18n( 'communityrequests-focus-area-description' ).text()"
			:description-help-text="
				$i18n( 'communityrequests-focus-area-description-description' ).text()
			"
			@update:description="formChanged = true"
			@update:pre-submit-promise="addPreSubmitFn"
		></description-section>
		<cdx-field
			class="ext-communityrequests-intake__short-description"
		>
			<cdx-text-area
				v-model="focusArea.shortdescription"
				name="shortdescription"
			></cdx-text-area>
			<template #label>
				{{ $i18n( 'communityrequests-focus-area-short-description' ).text() }}
			</template>
			<template #description>
				<!-- eslint-disable-next-line vue/no-v-html -->
				<span v-html="shortDescriptionHelpText"></span>
			</template>
		</cdx-field>
		<cdx-field
			class="ext-communityrequests-intake__owners"
			:optional="true"
		>
			<cdx-text-area
				v-model="focusArea.owners"
				name="owners"
				:placeholder="$i18n( 'communityrequests-focus-area-owners-placeholder' ).text()"
			></cdx-text-area>
			<template #label>
				{{ $i18n( 'communityrequests-focus-area-owners' ).text() }}
			</template>
			<template #description>
				{{ $i18n( 'communityrequests-focus-area-owners-description' ).text() }}
			</template>
		</cdx-field>
		<cdx-field
			class="ext-communityrequests-intake__volunteers"
			:optional="true"
		>
			<cdx-text-area
				v-model="focusArea.volunteers"
				name="volunteers"
				:placeholder="$i18n( 'communityrequests-focus-area-volunteers-placeholder' ).text()"
			></cdx-text-area>
			<template #label>
				{{ $i18n( 'communityrequests-focus-area-volunteers' ).text() }}
			</template>
			<template #description>
				{{ $i18n( 'communityrequests-focus-area-volunteers-description' ).text() }}
			</template>
		</cdx-field>
		<input
			:value="created"
			type="hidden"
			name="created"
		>
		<input
			:value="baserevid"
			type="hidden"
			name="baserevid"
		>
		<input
			:value="baselang"
			type="hidden"
			name="baselang"
		>

		<footer-section
			:exists="exists"
			:publish-msg="$i18n( 'publishpage' ).text()"
			:save-msg="$i18n( 'savechanges' ).text()"
			:return-to="returnTo"
			:form-error="formError"
			:form-error-msg="formErrorMsg"
			@submit="handleSubmit"
		></footer-section>
	</cdx-field>
</template>

<script>
/* eslint-disable vue/no-unused-properties */
const { computed, defineComponent, nextTick, onMounted, reactive, ref, ComputedRef, Ref } = require( 'vue' );
const { CdxField, CdxTextArea } = require( '../codex.js' );
const {
	CommunityRequestsHomepage,
	CommunityRequestsFocusAreaIndexPage,
	CommunityRequestsStatuses
} = require( '../common/config.json' );
const Util = require( '../common/Util.js' );
const StatusSection = require( './StatusSection.vue' );
const DescriptionSection = require( './DescriptionSection.vue' );
const FooterSection = require( './FooterSection.vue' );

const api = new mw.Api();
const defaultStatusKey = Object.keys( CommunityRequestsStatuses )
	.find( ( key ) => CommunityRequestsStatuses[ key ].default );
const titleMaxChars = mw.config.get( 'intakeTitleMaxChars' );

// Functions returning Promises that must be resolved before the form can be validated/submitted.
// This is outside the Vue component as it does not need to be reactive.
const preSubmitFns = [];

module.exports = exports = defineComponent( {
	name: 'SpecialEditFocusArea',
	components: {
		CdxField,
		CdxTextArea,
		StatusSection,
		DescriptionSection,
		FooterSection
	},
	props: {
		baselang: { type: String, default: mw.config.get( 'wgUserLanguage' ) },
		baserevid: { type: Number, default: 0 },
		created: { type: String, default: '' },
		description: { type: String, default: '' },
		shortdescription: { type: String, default: '' },
		owners: { type: String, default: '' },
		volunteers: { type: String, default: '' },
		status: {
			type: String,
			default: defaultStatusKey || Object.keys( CommunityRequestsStatuses )[ 0 ]
		},
		title: { type: String, default: '' }
	},
	setup( props ) {
		// Reactive properties

		/**
		 * Reactive object representing the focus area being edited or created.
		 *
		 * @type {Ref<Object>}
		 */
		const focusArea = reactive( Object.assign( {}, props ) );
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
		/**
		 * Help text for the short description field.
		 *
		 * @type {ComputedRef<string>}
		 */
		const shortDescriptionHelpText = computed( () => mw.message(
			'communityrequests-focus-area-short-description-description',
			CommunityRequestsFocusAreaIndexPage,
			CommunityRequestsHomepage
		).parse() );

		// Non-reactive properties

		/**
		 * Whether the user is a wishlist manager.
		 *
		 * @type {boolean}
		 */
		const isWishlistManager = Util.isWishlistManager();
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
			Util.getFocusAreaPageTitleFromId( mw.config.get( 'intakeId' ) ) :
			CommunityRequestsFocusAreaIndexPage
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
			const title = focusArea.title
				.replace( /<\/?translate>/g, '' )
				.replace( /<!--T:[0-9]+-->/g, '' );
			titleStatus.value = ( title.length < 5 || title.length > titleMaxChars ) ? 'error' : 'default';
			descriptionStatus.value = ( focusArea.description.length < 50 ) ? 'error' : 'default';
			return titleStatus.value !== 'error' &&
				descriptionStatus.value !== 'error';
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
		 */
		function handleSubmit() {
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
			formErrorMsg.value = api.getErrorMessage( error ).html();
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
			focusArea,
			titleStatus,
			descriptionStatus,
			formChanged,
			formDisabled,
			formError,
			formErrorMsg,
			shortDescriptionHelpText,
			isWishlistManager,
			exists,
			returnTo,
			addPreSubmitFn,
			handleSubmit
		};
	}
} );
</script>
