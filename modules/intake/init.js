'use strict';

const Util = require( '../common/Util.js' );
const { CommunityRequestsWishIndexPage } = require( '../common/config.json' );

if ( Util.isNewWish() || Util.isWishEdit() ) {
	const IntakeForm = require( './SpecialWishlistIntake.vue' );
	const Vue = require( 'vue' );
	const vm = Vue.createMwApp( IntakeForm, mw.config.get( 'intakeWishData' ) );
	vm.mount( '.ext-communityrequests-intake' );
} else if ( Util.isWishView() ) {
	showPostEditBanner();
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
	// View wishes link.
	const viewWishesLink = document.createElement( 'a' );
	viewWishesLink.href = mw.util.getUrl( CommunityRequestsWishIndexPage );
	viewWishesLink.textContent = mw.msg( 'communityrequests-view-all-wishes' );
	// Message content.
	const messageContent = document.createElement( 'div' );
	messageContent.className = 'cdx-message__content';
	const messageContentMsg = mw.config.get( 'intakePostEdit' ) === 'created' ?
		'communityrequests-create-success' :
		'communityrequests-edit-success';
	// Messages that can be used here:
	// * communityrequests-create-success
	// * communityrequests-edit-success
	messageContent.textContent = mw.msg( messageContentMsg ) + ' ';
	// Message container.
	const messageContainer = document.createElement( 'div' );
	messageContainer.className = 'cdx-message cdx-message--block cdx-message--success';
	messageContainer.ariaLive = 'polite';
	// Append elements.
	closeButton.appendChild( closeImg );
	messageContent.appendChild( viewWishesLink );
	messageContainer.appendChild( messageIcon );
	messageContainer.appendChild( messageContent );
	messageContainer.appendChild( closeButton );
	document.querySelector( '.mw-body-content' ).prepend( messageContainer );
	// Close the banner when the close button is clicked.
	closeButton.addEventListener( 'click', () => messageContainer.remove() );
}
