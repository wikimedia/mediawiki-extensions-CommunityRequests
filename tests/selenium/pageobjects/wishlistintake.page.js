import Page from 'wdio-mediawiki/Page';

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

	get descriptionEditable() {
		return this.descriptionInput.$( 'div[contenteditable="true"]' );
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

	get tagsInput() {
		return $( '.ext-communityrequests-intake__tags .cdx-chip-input__input' );
	}

	get firstTagOption() {
		return $( '.ext-communityrequests-intake__tags .cdx-menu-item' );
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

export default new WishlistIntakePage();
