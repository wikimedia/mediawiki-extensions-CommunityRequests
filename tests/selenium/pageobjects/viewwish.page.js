'use strict';

const Page = require( 'wdio-mediawiki/Page.js' );

class ViewWishPage extends Page {
	get successMessage() {
		return $( '.cdx-message--success' );
	}

	get statusChip() {
		return $( '.wishlist-intake-titleandstatus .cdx-info-chip' );
	}

	get wishTitle() {
		return $( '.wishlist-intake-titleandstatus' );
	}

	get description() {
		return $( '#Description' ).parentElement().nextElement();
	}

	get wishType() {
		return $( '#Type_of_wish' ).parentElement().nextElement();
	}

	get projects() {
		return $( '#Related_projects' ).parentElement().nextElement();
	}

	get audience() {
		return $( '#Affected_users' ).parentElement().nextElement();
	}
}

module.exports = new ViewWishPage();
