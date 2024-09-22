'use strict';

const Vue = require( 'vue' );
const { CdxMessage } = require( '@wikimedia/codex' );
const IntakeForm = require( './SpecialWishlistIntake.vue' );
const Util = require( '../common/Util.js' );
const Wish = require( '../common/Wish.js' );

const intakeWishTitle = mw.config.get( 'intakeWishTitle' );
const api = new mw.Api();

/**
 * If the page already exists, pre-fetch the content
 * so that it's available when the form is loaded.
 *
 * @return {Promise|jQuery.Promise}
 */
function loadWishData() {
	if ( Util.isNewWish() ) {
		return Promise.resolve();
	}

	return api.get( {
		action: 'query',
		format: 'json',
		prop: 'revisions',
		titles: intakeWishTitle,
		rvprop: [ 'content', 'timestamp' ],
		rvslots: 'main',
		formatversion: 2,
		assert: 'user',
		curtimestamp: true
	} ).then( ( res ) => {
		const page = res.query && res.query.pages ? res.query.pages[ 0 ] : {};
		if ( page.missing ) {
			// TODO: show button to create wish and pre-fill the title with the subpage name.
			return {};
		}
		const revision = page.revisions[ 0 ];
		const template = Util.getWishTemplate();
		const wikitext = revision.slots.main.content;
		const wish = template.getWish( wikitext, intakeWishTitle );
		// Confirm that we can parse the wikitext.
		if ( !wish ) {
			return null;
		}
		const wishData = {
			status: wish.status,
			type: wish.type,
			title: wish.title,
			description: wish.description,
			audience: wish.audience,
			tasks: Wish.getArrayFromValue( wish.tasks ),
			proposer: wish.proposer,
			created: wish.created,
			projects: Wish.getArrayFromValue( wish.projects ),
			otherproject: wish.otherproject,
			area: wish.area,
			baselang: wish.baselang
		};
		// Confirm that we can parse and then re-create the same wikitext.
		if ( template.getWikitext( wishData ) !== wikitext ) {
			Util.logError( 'Parsing failed for ', intakeWishTitle );
		}
		return Object.assign( {
			// For edit conflict detection.
			basetimestamp: revision.timestamp,
			curtimestamp: res.curtimestamp
		}, wishData );
	} );
}

/**
 * Show an error message when a wish fails to load.
 */
function handleWishLoadError() {
	const errorMsg = mw.message( 'communityrequests-wish-loading-error',
		window.location.href,
		`Special:EditPage/${ intakeWishTitle }`,
		'Talk:Community Wishlist'
	);
	Vue.createMwApp( {
		template: `<cdx-message type="error">${ errorMsg.parse() }</cdx-message>`,
		components: { CdxMessage }
	} ).mount( document.querySelector( '.wishlist-intake-container' ) );
}

/**
 * Load the form and mount it to the page.
 */
loadWishData().then( ( wishData ) => {
	if ( wishData === null ) {
		handleWishLoadError();
		return;
	}

	const root = document.querySelector( '.wishlist-intake-container' );
	Vue.createMwApp( IntakeForm, wishData ).mount( root );
} );
