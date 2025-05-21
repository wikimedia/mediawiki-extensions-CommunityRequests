'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class WishlistIntakePage extends Page {

	async open( title ) {
		await super.openTitle( `Special:WishlistIntake${ title ? '/' + title : '' }` );
	}

	get titleInput() {
		return $( '.ext-communityrequests-intake__title input' );
	}

	get titleError() {
		return $( '.ext-communityrequests-intake__title .cdx-message--error' );
	}

	get descriptionInput() {
		return $( '.ext-communityrequests-intake__ve-surface' );
	}

	get descriptionError() {
		return $( '.ext-communityrequests-intake__description .cdx-message--error' );
	}

	get firstWishTypeInput() {
		return $( '.ext-communityrequests-intake__type .cdx-radio__input' );
	}

	get typeError() {
		return $( '.ext-communityrequests-intake__type .cdx-message--error' );
	}

	get allProjectsCheckbox() {
		return $( '.ext-communityrequests-intake__project .cdx-checkbox__input' );
	}

	get otherProjectInput() {
		return $( '.ext-communityrequests-intake__project-other input' );
	}

	get projectsError() {
		return $( '.ext-communityrequests-intake__project .cdx-message--error' );
	}

	get audienceInput() {
		return $( '.ext-communityrequests-intake__audience input' );
	}

	get audienceError() {
		return $( '.ext-communityrequests-intake__audience .cdx-message--error' );
	}

	get phabricatorTasksInput() {
		return $( '.ext-communityrequests-intake__tasks input' );
	}

	get submitButton() {
		return $( '.ext-communityrequests-intake__submit' );
	}
}

module.exports = new WishlistIntakePage();
