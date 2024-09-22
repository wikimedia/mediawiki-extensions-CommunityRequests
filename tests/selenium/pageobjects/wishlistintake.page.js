'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class WishlistIntakePage extends Page {

	async open( title ) {
		await super.openTitle( `Special:WishlistIntake${ title ? '/' + title : '' }` );
	}

	get titleInput() {
		return $( '.community-wishlist-title-field input' );
	}

	get titleError() {
		return $( '.community-wishlist-title-field .cdx-message--error' );
	}

	get descriptionInput() {
		return $( '.wishlist-intake-ve-surface' );
	}

	get descriptionError() {
		return $( '.community-wishlist-description-field .cdx-message--error' );
	}

	get firstWishTypeInput() {
		return $( '.wishlist-intake-type .cdx-radio__input' );
	}

	get typeError() {
		return $( '.wishlist-intake-wishtype .cdx-message--error' );
	}

	get allProjectsCheckbox() {
		return $( '.wishlist-intake-project .cdx-checkbox__input' );
	}

	get otherProjectInput() {
		return $( '.wishlist-intake-project-other input' );
	}

	get projectsError() {
		return $( '.wishlist-intake-project .cdx-message--error' );
	}

	get audienceInput() {
		return $( '.wishlist-intake-audience input' );
	}

	get audienceError() {
		return $( '.wishlist-intake-audience .cdx-message--error' );
	}

	get phabricatorTasksInput() {
		return $( '.wishlist-intake-tasks input' );
	}

	get submitButton() {
		return $( '.wishlist-intake-submit' );
	}
}

module.exports = new WishlistIntakePage();
