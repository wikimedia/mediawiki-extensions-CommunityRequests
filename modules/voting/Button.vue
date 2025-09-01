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
		:title="dialogTitle"
		:use-close-button="true"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		@primary="onPrimaryAction"
		@default="open = false"
	>
		<cdx-field>
			<cdx-text-area
				:disabled="submitting"
				@input="updateComment"
			></cdx-text-area>
			<template #label>
				{{ $i18n( 'communityrequests-optional-comment' ).text() }}
			</template>
		</cdx-field>
	</cdx-dialog>
</template>

<script>
const { defineComponent, computed, onBeforeMount, ref } = require( 'vue' );
const { CdxButton, CdxDialog, CdxField, CdxIcon, CdxTextArea, CdxMessage } = require( '@wikimedia/codex' );
const { cdxIconCheck } = require( './icons.json' );
const Util = require( '../common/Util.js' );
const { CommunityRequestsVotesPageSuffix } = require( '../common/config.json' );

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
		const api = new mw.Api();
		const votesPageName = getBasePageName() + CommunityRequestsVotesPageSuffix;
		const open = ref( false );
		const hasVoted = ref( null );
		let comment = '';
		let showVotedMessage = ref( false );
		let submitting = ref( false );

		let basetimestamp = null;
		let curtimestamp = null;

		const defaultAction = computed( () => {
			const action = { label: mw.msg( 'cancel' ), disabled: submitting.value };
			return action;
		} );

		const dialogTitle = computed( () => {
			const title = mw.msg(
				'communityrequests-support-dialog-title',
				Util.getPageName()
			);
			return title;
		} );

		const primaryAction = computed( () => {
			const action = {
				label: mw.msg( 'communityrequests-support' ),
				actionType: 'progressive',
				disabled: submitting.value
			};
			return action;
		} );

		function updateComment( event ) {
			comment = event.target.value;
		}

		function onPrimaryAction() {
			submitting = true;
			loadVotes().then( ( votes ) => {
				addVote( votes ).then( ( editResult ) => {
					open.value = false;
					if ( !editResult ) {
						return;
					}
					if ( editResult.edit.result === 'Success' ) {
						// Purge and reload the page.
						const postArgs = { action: 'purge', titles: mw.config.get( 'wgPageName' ) };
						( new mw.Api() ).post( postArgs ).then( ( purgeRes ) => {
							mw.storage.session.set( 'wishlist-intake-vote-added', 1 );
							location.href = mw.util.getUrl( purgeRes.purge[ 0 ].title ) + '#voting';
							showVotedMessage.value = true;
							hasVoted.value = true;
						} );
					} else {
						// @todo Handle errors.
					}
				} );
			} );
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
				' |comment=' + comment.replace( /\|/g, '{{!}}' ) +
				' |username=' + mw.config.get( 'wgUserName' ) +
				' |timestamp=' + ( new Date() ).toISOString() +
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
				errorformat: 'html',
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
		function loadVotes() {
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
			} );
		}

		onBeforeMount( () => {
			loadVotes().then( ( votes ) => {
				hasVoted.value = votes.length > 0 && alreadyVoted( votes );
				if ( hasVoted.value ) {
					// Also check for the session storage param for the post-voting message.
					showVotedMessage = mw.storage.session.get( 'wishlist-intake-vote-added' ) !== null;
					mw.storage.session.remove( 'wishlist-intake-vote-added' );
				}
			} );
		} );

		return {
			cdxIconCheck,
			defaultAction,
			dialogTitle,
			hasVoted,
			isWishPage: Util.isWishPage(),
			onPrimaryAction,
			open,
			primaryAction,
			showVotedMessage,
			submitting,
			updateComment
		};
	}
} );
</script>

<style lang="less">
</style>
