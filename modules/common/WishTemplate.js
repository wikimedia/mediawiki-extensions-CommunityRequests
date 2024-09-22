const Wish = require( './Wish.js' );
const TemplateParser = require( './TemplateParser.js' );
const config = require( './config.json' );

class WishTemplate {

	constructor( templateName ) {
		this.templateName = templateName.replace( /^Template:/, '' );
	}

	/**
	 * @param {Object} wish
	 * @return {string}
	 */
	getWikitext( wish ) {
		const paramNames = [
			'status',
			'type',
			'title',
			'description',
			'audience',
			'tasks',
			'proposer',
			'created',
			'projects',
			'otherproject',
			'area',
			'baselang'
		];
		let out = '{{' + this.templateName + '\n';
		for ( const key of paramNames ) {
			const value = wish[ key ];
			if ( value === '' ) {
				out += `| ${ key } =\n`;
			} else if ( value !== undefined ) {
				out += `| ${ key } = ${ value }\n`;
			}
		}
		out += '}}';
		return out;
	}

	/**
	 * Get the Wish object from the given wikitext, or null if the wish template
	 * was not found on the page. Any <translate> tags in the wikitext will be
	 * removed.
	 *
	 * @param {string} wikitext
	 * @param {string} pageTitle
	 * @param {?number} pageId
	 * @param {string} updated
	 * @return {?Wish}
	 */
	getWish( wikitext, pageTitle = '', pageId = null, updated = '' ) {
		const data = TemplateParser.getParams( wikitext, this.templateName );
		if ( data === null ) {
			return null;
		}
		data.page = pageTitle;
		if ( pageId !== null ) {
			data.pageId = pageId;
		}
		if ( pageTitle.startsWith( config.CommunityRequestsWishPagePrefix ) ) {
			const relPage = pageTitle.slice( config.CommunityRequestsWishPagePrefix.length );
			const m = relPage.match( /(.*)\/([a-z0-9-]{2,})$/ );
			if ( m ) {
				data.name = m[ 1 ];
				data.lang = m[ 2 ];
			} else {
				data.name = relPage;
				data.lang = '';
			}
		} else {
			data.name = pageTitle;
		}
		data.updated = updated;
		return new Wish( data );
	}

	/**
	 * Strip <translate> tags from a string
	 *
	 * @param {string} text
	 * @return {string}
	 */
	stripTranslate( text ) {
		text = text.replace( /<translate( nowrap)?>\n?/g, '' );
		text = text.replace( /\n?<\/translate>/g, '' );
		text = text.replace( /<tvar\s+name\s*=\s*(('[^']*')|("[^"]*")|([^"'\s>]*))\s*>.*?<\/tvar\s*>/g, '' );
		text = text.replace( /(^=.*=) <!--T:[^_/\n<>]+-->$/mg, '$1' );
		text = text.replace( /<!--T:[^_/\n<>]+-->[\n ]?/g, '' );
		return text;
	}
}

module.exports = WishTemplate;
