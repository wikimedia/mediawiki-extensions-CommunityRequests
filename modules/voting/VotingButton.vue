<template>
	<div v-if="hasVoted" class="ext-communityrequests-voting--voted">
		<div>
			<div class="ext-communityrequests-voting--voted-message">
				{{ isWishPage ?
					$i18n( 'communityrequests-wish-already-supported' ).text() :
					$i18n( 'communityrequests-focus-area-already-supported' ).text()
				}}
			</div>
			<cdx-button
				action="progressive"
				weight="quiet"
				@click="open = true"
			>
				{{ $i18n( 'communityrequests-edit-support' ).text() }}
			</cdx-button>
			<cdx-button
				action="destructive"
				weight="quiet"
				@click="removeDialogOpen = true"
			>
				{{ $i18n( 'communityrequests-remove-support' ).text() }}
			</cdx-button>
		</div>
	</div>
	<cdx-button
		v-else
		action="progressive"
		weight="quiet"
		@click="open = true"
	>
		{{ isWishPage ?
			$i18n( 'communityrequests-support-wish' ).text() :
			$i18n( 'communityrequests-support-focus-area' ).text()
		}}
	</cdx-button>

	<cdx-dialog
		v-model:open="open"
		:class="dialogClass"
		:title="$i18n( 'communityrequests-support-dialog-title', entityTitle ).text()"
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
				v-model="commentModel"
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

	<cdx-dialog
		v-model:open="removeDialogOpen"
		:class="dialogClass"
		:title="$i18n( 'communityrequests-remove-support' ).text()"
		:use-close-button="true"
		:primary-action="{
			label: $i18n( 'communityrequests-remove-support-yes' ).text(),
			actionType: 'destructive',
			disabled: submitting
		}"
		:default-action="{
			label: $i18n( 'communityrequests-remove-support-no' ).text(),
			disabled: submitting
		}"
		@primary="onRemoveVote"
		@default="removeDialogOpen = false"
	>
		<cdx-field
			:status="submitStatus"
			:messages="submitMessages"
		>
			<p>{{ $i18n( 'communityrequests-remove-support-prompt', entityTitle ).text() }}</p>
		</cdx-field>
	</cdx-dialog>
</template>

<script>
const { defineComponent, computed, ref, ComputedRef, Ref } = require( 'vue' );
const { CdxButton, CdxDialog, CdxField, CdxTextArea } = require( '../codex.js' );
const Util = require( '../common/Util.js' );
const api = new mw.Api();
const isWishPage = Util.isWishPage();
const entityTitle = Util.getEntityTitle();

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
		CdxTextArea
	},
	props: {
		entity: { type: String, required: true },
		username: { type: String, default: null },
		comment: { type: String, default: '' }
	},
	setup( props ) {
		const hasVoted = !!props.username;

		// Reactive properties

		/**
		 * The value of the comment textarea.
		 *
		 * @type {Ref<string>}
		 */
		const commentModel = ref( props.comment );
		/**
		 * Whether the voting dialog is open.
		 *
		 * @type {Ref<boolean>}
		 */
		const open = ref( false );
		/**
		 * Whether the remove vote confirmation dialog is open.
		 *
		 * @type {Ref<boolean>}
		 */
		const removeDialogOpen = ref( false );
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
		 * The dialog CSS class.
		 *
		 * @type {ComputedRef<string>}
		 */
		const dialogClass = computed(
			() => 'ext-communityrequests-voting__dialog' +
				( submitting ? ' ext-communityrequests-voting__dialog--loading' : '' )
		);

		// Functions

		/**
		 * Use the action=wishlistvote API to cast or edit a vote.
		 */
		async function onPrimaryAction() {
			submitting.value = true;
			api.postWithEditToken( api.assertCurrentUser( {
				action: 'wishlistvote',
				entity: props.entity,
				comment: commentModel.value,
				voteaction: 'add',
				formatversion: 2,
				// Localize errors
				uselang: mw.config.get( 'wgUserLanguage' ),
				errorformat: 'plaintext',
				errorlang: mw.config.get( 'wgUserLanguage' ),
				errorsuselocal: true
			} ) ).then( async () => {
				submitStatus.value = 'default';
				submitMessages.value = {};
				// Reload the page.
				location.reload();
			} ).catch( apiErrorHandler );
		}
		/**
		 * Close and reset the form.
		 */
		async function onDefaultAction() {
			open.value = false;
			submitting.value = false;
			submitStatus.value = 'default';
			submitMessages.value = {};
		}
		/**
		 * Use the action=wishlistvote API to remove a vote.
		 */
		async function onRemoveVote() {
			submitting.value = true;
			api.postWithEditToken( api.assertCurrentUser( {
				action: 'wishlistvote',
				entity: props.entity,
				voteaction: 'remove',
				formatversion: 2,
				// Localize errors
				uselang: mw.config.get( 'wgUserLanguage' ),
				errorformat: 'plaintext',
				errorlang: mw.config.get( 'wgUserLanguage' ),
				errorsuselocal: true
			} ) ).then( async () => {
				// Reload the page.
				location.reload();
			} ).catch( apiErrorHandler );
		}
		/**
		 * Handler used when catching API request errors.
		 *
		 * @param {string} code
		 * @param {Object} response
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
		}

		return {
			hasVoted,
			commentModel,
			copyrightWarning: mw.config.get( 'copyrightWarning' ),
			defaultAction,
			dialogClass,
			entityTitle,
			isWishPage,
			onDefaultAction,
			onPrimaryAction,
			onRemoveVote,
			open,
			primaryAction,
			removeDialogOpen,
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
	.cdx-message {
		margin: @spacing-75 0;
	}

	&--voted {
		display: flex;
		gap: @spacing-50;
	}

	&--voted-message {
		font-size: 0.95em;
		position: relative;
		bottom: 5px;
	}
}
</style>
