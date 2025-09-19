'use strict';

const Util = require( '../common/Util.js' );
const { CommunityRequestsWishIndexPage, CommunityRequestsFocusAreaIndexPage } = require( '../common/config.json' );
const buttonRoot = document.querySelector( '.ext-communityrequests-voting' );
const crVoteData = mw.config.get( 'crVoteData', null );
const crPostEdit = mw.config.get( 'crPostEdit', '' );

if ( buttonRoot && crVoteData ) {
	const Vue = require( 'vue' );
	let VotingButton;
	if ( mw.user.isNamed() ) {
		VotingButton = require( './VotingButton.vue' );
	} else {
		VotingButton = require( './AuthButton.vue' );
	}
	Vue.createMwApp( VotingButton, crVoteData ).mount( buttonRoot );
}

if ( ( Util.isWishView() || Util.isFocusAreaView() ) && crPostEdit ) {
	showPostEditBanner();
}

/**
 * Show a banner after a wishlist entity has been saved or voted on.
 */
function showPostEditBanner() {
	// Close image.
	const closeImg = document.createElement( 'img' );
	closeImg.src = 'https://upload.wikimedia.org/wikipedia/commons/8/82/Codex_icon_close.svg';
	closeImg.alt = mw.msg( 'communityrequests-close' );
	// Close button.
	const closeButton = document.createElement( 'button' );
	// eslint-disable-next-line mediawiki/class-doc
	closeButton.className = [
		'cdx-button',
		'cdx-button--action-default',
		'cdx-button--weight-quiet',
		'cdx-button--size-medium',
		'cdx-button--icon-only',
		'cdx-message__dismiss-button'
	].join( ' ' );
	closeButton.ariaLabel = mw.msg( 'communityrequests-close' );
	// Message icon.
	const messageIcon = document.createElement( 'span' );
	messageIcon.className = 'cdx-message__icon';
	// View all link.
	const viewAllLink = document.createElement( 'a' );
	viewAllLink.href = mw.util.getUrl(
		Util.isWishView() ? CommunityRequestsWishIndexPage : CommunityRequestsFocusAreaIndexPage
	);
	viewAllLink.textContent = mw.msg(
		Util.isWishView() ? 'communityrequests-view-all-wishes' : 'communityrequests-view-all-focus-areas'
	);
	// Message content.
	const messageContent = document.createElement( 'div' );
	messageContent.className = 'cdx-message__content';
	const messageContentEntity = Util.isFocusAreaView() ? 'focus-area' : 'wish';
	let messageContentMsg;
	switch ( crPostEdit ) {
		case 'entity-created':
			messageContentMsg = `communityrequests-${ messageContentEntity }-create-success`;
			break;
		case 'entity-updated':
			messageContentMsg = `communityrequests-${ messageContentEntity }-edit-success`;
			break;
		case 'vote-added':
			messageContentMsg = `communityrequests-support-${ messageContentEntity }-confirmed`;
			break;
		case 'vote-updated':
			messageContentMsg = 'communityrequests-support-updated';
			break;
		case 'vote-removed':
			messageContentMsg = 'communityrequests-support-removed';
			break;
	}
	// Messages that can be used here:
	// * communityrequests-wish-create-success
	// * communityrequests-wish-edit-success
	// * communityrequests-focus-area-create-success
	// * communityrequests-focus-area-edit-success
	// * communityrequests-support-wish-confirmed
	// * communityrequests-support-focus-area-confirmed
	// * communityrequests-support-updated
	// * communityrequests-support-removed
	messageContent.textContent = mw.msg( messageContentMsg ) + ' ';
	// Message container.
	const messageContainer = document.createElement( 'div' );
	messageContainer.className = 'cdx-message cdx-message--block cdx-message--success';
	messageContainer.ariaLive = 'polite';
	// Append elements.
	closeButton.appendChild( closeImg );
	messageContent.appendChild( viewAllLink );
	messageContainer.appendChild( messageIcon );
	messageContainer.appendChild( messageContent );
	messageContainer.appendChild( closeButton );
	let containerParent;
	if ( crPostEdit.startsWith( 'entity-' ) ) {
		containerParent = document.querySelector( '.mw-body-content' );
		containerParent.prepend( messageContainer );
	} else {
		containerParent = document.querySelector( '.ext-communityrequests-voting' );
		containerParent.append( messageContainer );
	}
	containerParent.scrollIntoView( { behavior: 'smooth' } );
	// Close the banner when the close button is clicked.
	closeButton.addEventListener( 'click', () => messageContainer.remove() );
}
