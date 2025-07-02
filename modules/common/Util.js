const {
	CommunityRequestsWishPagePrefix,
	CommunityRequestsFocusAreaPagePrefix
} = require( './config.json' );

/**
 * Utility functions for Community Requests.
 */
class Util {

	/**
	 * Get the full page name with underscores replaced by spaces.
	 * We use this instead of wgTitle because it's possible to set up
	 * the wishlist gadget for use outside the mainspace.
	 *
	 * @return {string}
	 */
	static getPageName() {
		return mw.config.get( 'wgPageName' ).replace( /_/g, ' ' );
	}

	/**
	 * Is the current page a wish page?
	 *
	 * @return {boolean}
	 */
	static isWishPage() {
		return this.getPageName().startsWith( CommunityRequestsWishPagePrefix );
	}

	/**
	 * Are we currently creating a new wish?
	 *
	 * @return {boolean}
	 */
	static isNewWish() {
		return mw.config.get( 'wgCanonicalSpecialPageName' ) === 'WishlistIntake' &&
			!this.isWishEdit();
	}

	/**
	 * Are we currently viewing (but not editing) a wish page?
	 *
	 * @return {boolean}
	 */
	static isWishView() {
		return this.isWishPage() && mw.config.get( 'wgAction' ) === 'view';
	}

	/**
	 * Are we currently editing a wish page?
	 *
	 * @return {boolean}
	 */
	static isWishEdit() {
		return mw.config.get( 'wgCanonicalSpecialPageName' ) === 'WishlistIntake' &&
			!!mw.config.get( 'intakeId' );
	}

	/**
	 * Are we currently manually editing a wish page?
	 *
	 * @return {boolean}
	 */
	static isManualWishEdit() {
		return this.isWishPage() &&
			(
				mw.config.get( 'wgAction' ) === 'edit' ||
				document.documentElement.classList.contains( 've-active' )
			);
	}

	/**
	 * Is the current page a focus area page?
	 *
	 * @return {boolean}
	 */
	static isFocusAreaPage() {
		return this.getPageName().startsWith( CommunityRequestsFocusAreaPagePrefix );
	}

	/**
	 * Are we currently creating a new focus area?
	 *
	 * @return {boolean}
	 */
	static isNewFocusArea() {
		return mw.config.get( 'wgCanonicalSpecialPageName' ) === 'EditFocusArea' &&
			!this.isFocusAreaEdit();
	}

	/**
	 * Are we currently viewing (but not editing) a focus area page?
	 *
	 * @return {boolean}
	 */
	static isFocusAreaView() {
		return this.isFocusAreaPage() && mw.config.get( 'wgAction' ) === 'view';
	}

	/**
	 * Are we currently editing a focus area page?
	 *
	 * @return {boolean}
	 */
	static isFocusAreaEdit() {
		return mw.config.get( 'wgCanonicalSpecialPageName' ) === 'EditFocusArea' &&
			!!mw.config.get( 'intakeId' );
	}

	/**
	 * Are we currently manually editing a focus area page?
	 *
	 * @return {boolean}
	 */
	static isManualFocusAreaEdit() {
		return this.isFocusAreaPage() &&
			(
				mw.config.get( 'wgAction' ) === 'edit' ||
				document.documentElement.classList.contains( 've-active' )
			);
	}

	/**
	 * Get the user's preferred language.
	 *
	 * @return {string}
	 */
	static userPreferredLang() {
		if ( this.isNewWish() ) {
			// Use interface language for new pages.
			return mw.config.get( 'wgUserLanguage' );
		}
		// Use content language for existing pages.
		return mw.config.get( 'wgContentLanguage' );
	}

	/**
	 * Is the user's preferred language right-to-left?
	 *
	 * @return {boolean}
	 */
	static isRtl() {
		return window.getComputedStyle( document.body ).direction === 'rtl';
	}

	/**
	 * Get the full page title of the wish from the ID.
	 *
	 * @param {number} id
	 * @return {string}
	 */
	static getWishPageTitleFromId( id ) {
		return CommunityRequestsWishPagePrefix + id;
	}

	/**
	 * Get the full page title of the focus area from the ID.
	 *
	 * @param {number} id
	 * @return {string}
	 */
	static getFocusAreaPageTitleFromId( id ) {
		return CommunityRequestsFocusAreaPagePrefix + id;
	}

	/**
	 * Does the user have the manage-wishlist user right?
	 *
	 * @return {boolean}
	 */
	static isWishlistManager() {
		return mw.config.get( 'intakeWishlistManager' );
	}

	/**
	 * Log an error to the console.
	 *
	 * @param {string} text
	 * @param {Error} error
	 */
	static logError( text, error ) {
		mw.log.error( `[CommunityRequests] ${ text }`, error );
	}

	/**
	 * Is the user viewing in mobile format?
	 *
	 * @return {boolean}
	 */
	static isMobile() {
		return !!mw.config.get( 'wgMFMode' );
	}
}

module.exports = Util;
