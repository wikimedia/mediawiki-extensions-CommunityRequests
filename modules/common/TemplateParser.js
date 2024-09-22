const TemplateParserError = require( './TemplateParserError.js' );

/**
 * Extension tag names
 *
 * @type {string[]}
 */
const EXT_NAMES = mw.config.get( 'parserTags' );

module.exports = class TemplateParser {
	/**
	 * @callback TemplateCallback
	 * @param {string} name
	 * @param {Object<string>} args
	 */

	/**
	 * Parse some wikitext and call a function for each template.
	 *
	 * Throw a TemplateParserError if the input text does not match our
	 * restricted grammar.
	 *
	 * @param {string} text
	 * @param {TemplateCallback} callback
	 */
	static parse( text, callback ) {
		const parser = new TemplateParser( text, callback );
		parser.execute();
	}

	/**
	 * Parse some wikitext and extract the parameters of the first template
	 * with a name matching the specified name.
	 *
	 * If the template was not found, or if there was a parse error, return
	 * null.
	 *
	 * @param {string} text
	 * @param {string} targetTemplateName
	 * @return {?Object<string>}
	 */
	static getParams( text, targetTemplateName ) {
		function normalize( name ) {
			name = name.charAt( 0 ).toUpperCase() +
				name.slice( 1 );
			return name.replaceAll( '_', ' ' );
		}

		targetTemplateName = normalize( targetTemplateName );
		let foundParams = null;
		try {
			TemplateParser.parse(
				text,
				// eslint-disalbe-next-line prefer-arrow-callback
				( templateName, args ) => {
					if ( foundParams === null &&
						normalize( templateName ) === targetTemplateName
					) {
						foundParams = args;
					}
				}
			);
		} catch ( e ) {
			if ( !( e instanceof TemplateParserError ) ) {
				throw e;
			}
		}
		return foundParams;
	}

	/**
	 * @param {string} text
	 * @param {TemplateCallback} callback
	 */
	constructor( text, callback ) {
		this.text = text;
		this.callback = callback;
	}

	/**
	 * Run the parser
	 */
	execute() {
		this.consumeWikitext( 0, [] );
	}

	/**
	 * Consume the top-level grammar rule
	 *
	 * @param {number} pos The offset at which to begin parsing
	 * @param {string[]} terminators Markup which, if encountered, causes
	 *   the function to return
	 * @return {number} The new offset beyond the end
	 */
	consumeWikitext( pos, terminators ) {
		while ( pos < this.text.length ) {
			for ( const terminator of terminators ) {
				if ( this.text.startsWith( terminator, pos ) ) {
					return pos;
				}
			}

			const char = this.text.charAt( pos );
			const char2 = this.text.slice( pos, pos + 2 );
			if ( char2 === '}}' ) {
				break;
			} else if ( char2 === '{{' ) {
				pos = this.consumeTemplate( pos );
			} else if ( char2 === '[[' ) {
				pos = this.consumeLink( pos );
			} else if ( char2 === '<!' &&
				this.text.slice( pos, pos + 4 ) === '<!--'
			) {
				pos = this.consumeComment( pos );
			} else if ( char === '<' &&
				EXT_NAMES.includes( this.getExtName( pos + 1 ) )
			) {
				pos = this.consumeExtension( pos );
			} else {
				pos = this.consumeLiteral( pos );
			}
		}
		return pos;
	}

	/**
	 * Consume a template call
	 *
	 * @param {number} pos The offset of the start markup "{{"
	 * @return {number} The offset beyond the end
	 */
	consumeTemplate( pos ) {
		let nextArgIndex = 1;
		pos = this.consumeMarkup( pos, '{{' );
		const nameStart = pos;
		pos = this.consumeWikitext( pos, [ '|', '}}' ] );
		const templateName = this.text.slice( nameStart, pos ).trim();
		const templateParams = {};
		while ( pos < this.text.length && this.text.charAt( pos ) === '|' ) {
			pos = this.consumeMarkup( pos, '|' );
			const partStart = pos;
			let name, value;
			pos = this.consumeWikitext( pos, [ '=', '|', '}}' ] );
			if ( this.text.charAt( pos ) === '=' ) {
				name = this.text.slice( partStart, pos ).trim();
				pos++;
				const valueStart = pos;
				pos = this.consumeWikitext( pos, [ '|', '}}' ] );
				value = this.text.slice( valueStart, pos ).trim();
			} else {
				name = nextArgIndex++;
				value = this.text.slice( partStart, pos );
			}
			templateParams[ name ] = value;
		}
		this.callback( templateName, templateParams );
		pos = this.consumeMarkup( pos, '}}' );
		return pos;
	}

	/**
	 * Consume a link
	 *
	 * @param {number} pos The offset of the start markup "[["
	 * @return {number} The offset beyond the end
	 */
	consumeLink( pos ) {
		pos = this.consumeMarkup( pos, '[[' );
		pos = this.consumeWikitext( pos, [ ']]' ] );
		pos = this.consumeMarkup( pos, ']]' );
		return pos;
	}

	/**
	 * Consume an HTML comment
	 *
	 * @param {number} pos The offset of the start markup "<!--"
	 * @return {number} The offset beyond the end
	 */
	consumeComment( pos ) {
		pos = this.consumeMarkup( pos, '<!--' );
		const endPos = this.text.indexOf( '-->', pos );
		if ( endPos === -1 ) {
			this.error( pos, 'missing comment terminator' );
		}
		pos = endPos + '-->'.length;
		return pos;
	}

	/**
	 * Consume an xmlish extension element
	 *
	 * @param {number} pos The offset of the start markup
	 * @return {number} The offset beyond the end
	 */
	consumeExtension( pos ) {
		pos = this.consumeMarkup( pos, '<' );
		const name = this.getExtName( pos );
		pos += name.length;
		const tagEndPos = this.text.indexOf( '>', pos );
		if ( tagEndPos === -1 ) {
			this.error( pos, 'missing end of extension tag' );
		}
		pos = tagEndPos + 1;
		if ( this.text.charAt( tagEndPos - 1 ) === '/' ) {
			// Self-closing
			return pos;
		} else {
			const endTag = `</${ name }>`;
			const endPos = this.text.indexOf( endTag, pos );
			if ( endPos === -1 ) {
				this.error( pos, 'missing extension end tag' );
			}
			return endPos + endTag.length;
		}
	}

	/**
	 * Consume at least one literal character
	 *
	 * @param {number} pos
	 * @return {number} The offset beyond the end of the run of literal characters
	 */
	consumeLiteral( pos ) {
		const literal = this.text.slice( pos ).match( /^[^[{|=}\]<]*/ )[ 0 ];
		if ( literal.length ) {
			// Literal composed of uninteresting characters
			return pos + literal.length;
		}
		if ( pos < this.text.length ) {
			// Literal composed of one interesting character not consumed elsewhere
			return pos + 1;
		}
		return pos;
	}

	/**
	 * Assert that the specified characters exist at the specified location
	 *
	 * @param {number} pos
	 * @param {string} markup
	 * @return {number} The position beyond the end of the markup
	 */
	consumeMarkup( pos, markup ) {
		if ( this.text.slice( pos, pos + markup.length ) !== markup ) {
			this.error( pos, `expected "${ markup }"` );
		}
		return pos + markup.length;
	}

	/**
	 * Look ahead to find the name of a prospective xmlish extension tag
	 *
	 * @param {number} pos
	 * @return {string}
	 */
	getExtName( pos ) {
		return this.text.slice( pos ).match( /^[a-zA-Z0-9_-]*/ )[ 0 ];
	}

	/**
	 * Raise a parse error
	 *
	 * @param {number} pos
	 * @param {string} message
	 */
	error( pos, message ) {
		throw new TemplateParserError( `Syntax error parsing template at offset ${ pos }: ${ message }` );
	}
};
