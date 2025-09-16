<template>
	<cdx-button
		v-if="hasVoted === false"
		action="progressive"
		weight="primary"
		@click="open = true"
	>
		{{ isWishPage ?
			$i18n( 'communityrequests-support-wish' ).text() :
			$i18n( 'communityrequests-support-focus-area' ).text()
		}}
	</cdx-button>
	<cdx-button
		v-else-if="hasVoted === true"
	>
		<cdx-icon :icon="cdxIconCheck"></cdx-icon>
		{{ $i18n( 'communityrequests-supported' ).text() }}
	</cdx-button>

	<cdx-message
		v-if="showVotedMessage"
		type="success"
		allow-user-dismiss
	>
		{{ isWishPage ?
			$i18n( 'communityrequests-support-wish-confirmed' ).text() :
			$i18n( 'communityrequests-support-focus-area-confirmed' ).text()
		}}
	</cdx-message>

	<cdx-dialog
		v-model:open="open"
		:class="dialogClass"
		:title="dialogTitle"
		:use-close-button="true"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		@primary="onPrimaryAction"
		@default="onDefaultAction"
	>
		<cdx-field
			:disabled="submitting"
			:status="submitStatus"
			:messages="submitMessages"
		>
			<cdx-text-area
				v-model="comment"
				:disabled="submitting"
			></cdx-text-area>
			<template #label>
				{{ $i18n( 'communityrequests-optional-comment' ).text() }}
			</template>
			<template #help-text>
				<!-- eslint-disable-next-line vue/no-v-html -->
				<div v-html="copyrightWarning"></div>
			</template>
		</cdx-field>
	</cdx-dialog>
</template>

<script>
const { defineComponent, computed, onBeforeMount, ref, ComputedRef, Ref } = require( 'vue' );
const { CdxButton, CdxDialog, CdxField, CdxIcon, CdxTextArea, CdxMessage } = require( '@wikimedia/codex' );
const { cdxIconCheck } = require( './icons.json' );
const Util = require( '../common/Util.js' );
const { CommunityRequestsVotesPageSuffix } = require( '../common/config.json' );

/**
 * @typedef ModalAction
 * @see https://doc.wikimedia.org/codex/latest/components/types-and-constants.html#modalaction
 */
/**
 * @typedef PrimaryModalAction
 * @see https://doc.wikimedia.org/codex/latest/components/types-and-constants.html#primarymodalaction
 */
/**
 * @typedef {string} ValidationStatusType
 * @see https://doc.wikimedia.org/codex/latest/components/types-and-constants.html#validationstatustype
 */
/**
 * @typedef {Object} ValidationMessages
 * @see https://doc.wikimedia.org/codex/latest/components/types-and-constants.html#validationmessages
 */

module.exports = exports = defineComponent( {
	name: 'VotingButton',
	components: {
		CdxButton,
		CdxDialog,
		CdxField,
		CdxIcon,
		CdxTextArea,
		CdxMessage
	},
	setup() {
		// Reactive properties

		/**
		 * The value of the comment textarea.
		 *
		 * @type {Ref<string>}
		 */
		const comment = ref( '' );
		/**
		 * Whether the voting dialog is open.
		 *
		 * @type {Ref<boolean>}
		 */
		const open = ref( false );
		/**
		 * Whether the current user has already voted.
		 *
		 * @type {Ref<boolean|null>} Null until loaded.
		 */
		const hasVoted = ref( null );
		/**
		 * Whether to show the success message.
		 *
		 * @type {Ref<boolean>}
		 */
		const showVotedMessage = ref( false );
		/**
		 * Whether the form is being submitted.
		 *
		 * @type {Ref<boolean>}
		 */
		const submitting = ref( false );
		/**
		 * The status of the comment field.
		 *
		 * @type {Ref<ValidationStatusType>}
		 */
		const submitStatus = ref( 'default' );
		/**
		 * The messages for the comment field.
		 *
		 * @type {Ref<ValidationMessages>}
		 */
		const submitMessages = ref( [] );

		// Computed properties

		/**
		 * The default action for the dialog (cancel button).
		 *
		 * @type {ComputedRef<ModalAction>}
		 */
		const defaultAction = computed( () => ( {
			label: mw.msg( 'cancel' ),
			disabled: submitting.value
		} ) );
		/**
		 * The primary action for the dialog (submit button).
		 *
		 * @type {ComputedRef<PrimaryModalAction>}
		 */
		const primaryAction = computed( () => ( {
			label: mw.msg( 'communityrequests-support' ),
			actionType: 'progressive',
			disabled: submitting.value
		} ) );
		/**
		 * The dialog title.
		 *
		 * @type {ComputedRef<string>}
		 */
		const dialogTitle = computed( () => mw.msg(
			'communityrequests-support-dialog-title',
			Util.getEntityTitle()
		) );
		/**
		 * The dialog CSS class.
		 *
		 * @type {ComputedRef<string>}
		 */
		const dialogClass = computed(
			() => 'ext-communityrequests-voting__dialog' +
				( submitting ? ' ext-communityrequests-voting__dialog--loading' : '' )
		);

		// Non-reactive properties

		const api = new mw.Api();
		const votesPageName = getBasePageName() + CommunityRequestsVotesPageSuffix;
		let basetimestamp = null;
		let curtimestamp = null;

		// Functions

		/**
		 * Handle the primary action (submit) of the dialog.
		 */
		async function onPrimaryAction() {
			submitting.value = true;
			const votes = await loadVotes();

			addVote( votes ).then( async () => {
				submitStatus.value = 'default';
				submitMessages.value = {};
				hasVoted.value = true;
				open.value = false;
				mw.storage.session.set( 'wishlist-intake-vote-added', 1 );
				// Purge the cache and reload the page.
				const postArgs = { action: 'purge', titles: mw.config.get( 'wgPageName' ) };
				const purgeRes = await ( new mw.Api() ).post( postArgs );
				location.href = mw.util.getUrl( purgeRes.purge[ 0 ].title ) + '#Voting';
				location.reload();
			} ).catch( apiErrorHandler )
				.always( () => {
					submitting.value = false;
				} );
		}
		/**
		 * Close and reset the form.
		 */
		async function onDefaultAction() {
			open.value = false;
			submitting.value = false;
			submitStatus.value = 'default';
			submitMessages.value = {};
			comment.value = '';
		}
		/**
		 * Get the name of the Translate 'source' page for this page.
		 *
		 * @return {string}
		 */
		function getBasePageName() {
			let pageName = mw.config.get( 'wgPageName' );
			if ( mw.config.get( 'wgTranslatePageTranslation' ) === 'translation' ) {
				pageName = pageName.slice( 0, pageName.length - mw.config.get( 'wgPageContentLanguage' ).length - 1 );
			}
			return pageName;
		}
		/**
		 * Add a vote template to the votes list, and save the full wikitext
		 * for both the Votes page and the Vote_count page.
		 *
		 * @param {string} votes The votes wikitext.
		 * @return {Promise}
		 */
		function addVote( votes ) {
			if ( alreadyVoted( votes ) ) {
				// @todo If already voted, change the timestamp and comment of the existing vote.
				return Promise.resolve();
			}

			const newVote = '{{#CommunityRequests: vote' +
				' |comment=' + comment.value.replace( /\|/g, '{{!}}' ) +
				' |username=' + mw.config.get( 'wgUserName' ) +
				' |timestamp=' + ( new Date() ).toISOString().replace( /\.\d+Z$/, 'Z' ) +
				' }}';
			votes = votes.trim() + '\n' + newVote;
			// Save the votes page.
			return api.postWithEditToken( api.assertCurrentUser( {
				action: 'edit',
				title: votesPageName,
				text: votes,
				formatversion: 2,
				// Protect against conflicts
				basetimestamp: basetimestamp,
				starttimestamp: curtimestamp,
				// Localize errors
				uselang: mw.config.get( 'wgUserLanguage' ),
				errorformat: 'plaintext',
				errorlang: mw.config.get( 'wgUserLanguage' ),
				errorsuselocal: true
			} ) );
		}
		/**
		 * Check if the current user has already voted.
		 *
		 * @param {string} votesWikitext
		 * @return {boolean}
		 */
		function alreadyVoted( votesWikitext ) {
			const escapedUsername = mw.util.escapeRegExp( mw.config.get( 'wgUserName' ) );
			// eslint-disable-next-line security/detect-non-literal-regexp
			const regex = new RegExp( 'username\\s*=\\s*' + escapedUsername );
			return votesWikitext.match( regex ) !== null;
		}
		/**
		 * Load the current page's votes data.
		 *
		 * @return {Promise<string>}
		 */
		async function loadVotes() {
			return api.get( {
				action: 'query',
				titles: votesPageName,
				prop: 'revisions',
				rvprop: [ 'content', 'timestamp' ],
				rvslots: 'main',
				curtimestamp: true,
				assert: 'user',
				format: 'json',
				formatversion: 2
			} ).then( ( response ) => {
				const page = response.query && response.query.pages ?
					response.query.pages[ 0 ] : {};

				if ( page.missing ) {
					return '';
				}
				curtimestamp = response.curtimestamp;
				basetimestamp = page.revisions[ 0 ].timestamp;
				return page.revisions[ 0 ].slots.main.content;
			} ).catch( apiErrorHandler );
		}

		/**
		 * Handler used when catching API request errors.
		 *
		 * @param {string} code
		 * @param {Object} response
		 * @return {string}
		 */
		function apiErrorHandler( code, response ) {
			let errorMessage = mw.msg( 'communityrequests-support-error' );
			if ( response && response.error && response.error.info ) {
				errorMessage = response.error.info;
			} else if ( response && response.errors && response.errors[ 0 ] ) {
				errorMessage = response.errors[ 0 ].text;
			} else {
				mw.log.error( response );
			}
			submitStatus.value = 'error';
			submitMessages.value = { error: errorMessage };
			submitting.value = false;
			return '';
		}

		// Lifecycle hooks

		onBeforeMount( async () => {
			const votes = await loadVotes();
			hasVoted.value = votes.length > 0 && alreadyVoted( votes );
			if ( hasVoted.value ) {
				// Also check for the session storage param for the post-voting message.
				showVotedMessage.value = mw.storage.session.get( 'wishlist-intake-vote-added' ) !== null;
				mw.storage.session.remove( 'wishlist-intake-vote-added' );
			}
		} );

		return {
			cdxIconCheck,
			comment,
			copyrightWarning: mw.config.get( 'copyrightWarning' ),
			defaultAction,
			dialogClass,
			dialogTitle,
			hasVoted,
			isWishPage: Util.isWishPage(),
			onDefaultAction,
			onPrimaryAction,
			open,
			primaryAction,
			showVotedMessage,
			submitMessages,
			submitStatus,
			submitting
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-communityrequests-voting {
	.cdx-button,
	.cdx-message {
		margin-bottom: @spacing-75;
	}
}
</style>
