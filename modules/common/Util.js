const {
	CommunityRequestsWishPagePrefix,
	CommunityRequestsFocusAreaPagePrefix,
	CommunityRequestsTags
} = require( './config.json' );
const tagsConfig = CommunityRequestsTags.navigation;
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
	 * Attempt to get the title of the current wish or focus area from the DOM.
	 * We can't pass in the title with JS vars from the server because the voting
	 * module may be loaded after the parser output is cached.
	 *
	 * If we can't find the title in the DOM, fallback to the page name.
	 *
	 * @return {string} HTML-escaped title
	 */
	static getEntityTitle() {
		const entityTitleQuery = document.querySelector(
			`.ext-communityrequests-${ this.isWishPage() ? 'wish' : 'focus-area' }--title`
		);
		if ( entityTitleQuery ) {
			return entityTitleQuery.textContent.trim();
		}
		return this.getPageName();
	}

	/**
	 * Get the localized status label for a wish.
	 *
	 * @param {string} status
	 * @return {string}
	 */
	static wishStatus( status ) {
		// Messages used here include:
		// * communityrequests-status-wish-under-review
		// * communityrequests-status-wish-declined
		// * communityrequests-status-wish-community-opportunity
		// * communityrequests-status-wish-long-term-opportunity
		// * communityrequests-status-wish-near-term-opportunity
		// * communityrequests-status-wish-prioritized
		// * communityrequests-status-wish-in-progress
		// * communityrequests-status-wish-done
		return mw.message( `communityrequests-status-wish-${ status }` ).text();
	}

	/**
	 * Get the localized tag label based on its value.
	 *
	 * @param {string} value
	 * @return {string|null } Tag label, or null if not found
	 */
	static getTagLabel( value ) {
		if ( Object.prototype.hasOwnProperty.call( tagsConfig, value ) ) {
			// Messages are configurable but by default will include:
			// * communityrequests-tag-admins
			// * communityrequests-tag-bots-gadgets
			// * communityrequests-tag-categories
			// * communityrequests-tag-citations
			// * communityrequests-tag-editing
			// * communityrequests-tag-hackathonable
			// * communityrequests-tag-ios
			// * communityrequests-tag-android
			// * communityrequests-tag-mobile-web
			// * communityrequests-tag-multimedia-commons
			// * communityrequests-tag-newcomers
			// * communityrequests-tag-notifications
			// * communityrequests-tag-patrolling
			// * communityrequests-tag-reading
			// * communityrequests-tag-search
			// * communityrequests-tag-talk-pages
			// * communityrequests-tag-templates
			// * communityrequests-tag-translation
			// * communityrequests-tag-watchlist-rc
			// * communityrequests-tag-wikidata
			// * communityrequests-tag-wikisource
			// * communityrequests-tag-wiktionary
			return mw.msg(
				tagsConfig[ value ].label ?
					tagsConfig[ value ].label :
					`communityrequests-tag-${ value }`
			);
		}
		return null;
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
