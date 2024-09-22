const config = require( './config.json' );
const WishlistTemplate = require( './WishTemplate.js' );

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
		return mw.config.get( 'wgPageName' ).replaceAll( '_', ' ' );
	}

	/**
	 * Is the current page a wish page?
	 *
	 * @return {boolean}
	 */
	static isWishPage() {
		return this.getPageName().startsWith( config.CommunityRequestsWishPagePrefix );
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
			!!mw.config.get( 'intakeWishTitle' );
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
	 * Get the slug for the wish derived from the page title.
	 * This is the subpage title and not necessarily the wish title,
	 * which is stored in the proposal content.
	 *
	 * @return {string|null} null if not a wish-related page
	 */
	static getWishSlug() {
		if ( this.isNewWish() ) {
			// New wishes have no slug yet.
			return '';
		} else if ( this.isWishEdit() ) {
			// Editing an existing wish.
			return mw.config.get( 'intakeWishTitle' )
				.slice( config.CommunityRequestsWishPagePrefix.length );
		} else if ( this.isWishPage() ) {
			// Existing wishes have the page prefix stripped.
			const slugPortion = this.getPageName()
				.slice( config.CommunityRequestsWishPagePrefix.length );
			// Strip off language subpage. Slashes are disallowed in wish slugs.
			return slugPortion.split( '/' )[ 0 ];
		}
		return null;
	}

	/**
	 * Get the user's preferred language.
	 *
	 * @return {string}
	 */
	static userPreferredLang() {
		if ( mw.config.get( 'wgArticleId' ) === 0 ) {
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
	 * Get the full page title of the wish from the slug.
	 *
	 * @param {string} slug
	 * @return {string}
	 */
	static getWishPageTitleFromSlug( slug ) {
		return config.CommunityRequestsWishPagePrefix + slug;
	}

	/**
	 * Is the user WMF staff?
	 *
	 * @todo Replace with proper user right
	 * @return {boolean}
	 */
	static isStaff() {
		return /\s\(WMF\)$|-WMF$/.test( mw.config.get( 'wgUserName' ) );
	}

	/**
	 * Log an error to the console.
	 *
	 * @param {string} text
	 * @param {Error} error
	 */
	static logError( text, error ) {
		mw.log.error( `[WishlistIntake] ${ text }`, error );
	}

	/**
	 * Is the user viewing in mobile format?
	 *
	 * @return {boolean}
	 */
	static isMobile() {
		return !!mw.config.get( 'wgMFMode' );
	}

	/**
	 * Get the wish template object.
	 *
	 * @return {WishlistTemplate}
	 */
	static getWishTemplate() {
		return new WishlistTemplate( config.CommunityRequestsWishTemplate );
	}
}

module.exports = Util;
