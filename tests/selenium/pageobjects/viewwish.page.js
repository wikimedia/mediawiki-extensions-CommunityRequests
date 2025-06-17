'use strict';

const Page = require( 'wdio-mediawiki/Page.js' );

class ViewWishPage extends Page {
	get successMessage() {
		return $( '.cdx-message--success' );
	}

	get statusChip() {
		return $( '.ext-communityrequests-wish--status' );
	}

	get wishTitle() {
		return $( '.ext-communityrequests-wish--title' );
	}

	get description() {
		return $( '.ext-communityrequests-wish--description' );
	}

	get wishType() {
		return $( '.ext-communityrequests-wish--wish-type' );
	}

	get projects() {
		return $( '.ext-communityrequests-wish--projects' );
	}

	get audience() {
		return $( '.ext-communityrequests-wish--audience' );
	}

	get phabTasks() {
		return $( '.ext-communityrequests-wish--phab-tasks' );
	}

	get proposer() {
		return $( '.ext-communityrequests-wish--proposer' );
	}
}

module.exports = new ViewWishPage();
