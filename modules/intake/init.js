'use strict';

const Util = require( '../common/Util.js' );
const {
	CommunityRequestsWishIndexPage,
	CommunityRequestsFocusAreaIndexPage
} = require( '../common/config.json' );

let form;

if ( Util.isNewWish() || Util.isWishEdit() ) {
	form = require( './SpecialWishlistIntake.vue' );
} else if ( Util.isNewFocusArea() || Util.isFocusAreaEdit() ) {
	form = require( './SpecialEditFocusArea.vue' );
} else if ( ( Util.isWishView() || Util.isFocusAreaView() ) && mw.config.get( 'intakePostEdit' ) ) {
	showPostEditBanner();
}

if ( form ) {
	const Vue = require( 'vue' );
	const vm = Vue.createMwApp( form, mw.config.get( 'intakeData' ) );
	vm.mount( '.ext-communityrequests-intake' );
}

/**
 * Show a banner after a wish has been saved.
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
	const messageContentEntity = Util.isFocusAreaView() ? 'focus-area-' : '';
	const messageContentMsg = mw.config.get( 'intakePostEdit' ) === 'created' ?
		`communityrequests-${ messageContentEntity }create-success` :
		`communityrequests-${ messageContentEntity }edit-success`;
	// Messages that can be used here:
	// * communityrequests-create-success
	// * communityrequests-edit-success
	// * communityrequests-focus-area-create-success
	// * communityrequests-focus-area-edit-success
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
	document.querySelector( '.mw-body-content' ).prepend( messageContainer );
	// Close the banner when the close button is clicked.
	closeButton.addEventListener( 'click', () => messageContainer.remove() );
}
