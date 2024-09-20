( function () {
	'use strict';
	const Vue = require( 'vue' );
	const TranslationBanner = require( './TranslationBanner.vue' );
	const mwStorage = require( 'mediawiki.storage' );

	mw.hook( 'wikipage.content' ).add( ( $content ) => {
		const targetLang = mw.config.get( 'wgUserLanguage' );
		getTranslatableNodes( $content[ 0 ], targetLang ).then( ( translatableNodes ) => {
			// Do nothing if there's nothing to translate.
			if ( translatableNodes.length === 0 ) {
				return;
			}

			// Mount the Vue app.
			const appRoot = document.createElement( 'div' );
			$content[ 0 ].before( appRoot );
			const appData = {
				targetLang: targetLang,
				// @todo Get the lang dir in a better way.
				targetLangDir: document.querySelector( 'html' ).dir,
				translatableNodes: translatableNodes
			};
			Vue.createMwApp( TranslationBanner, appData ).mount( appRoot );
		} );
	} );

	/**
	 * Get all source languages supported by MinT for the given target language,
	 * caching the result in localStorage for a day to avoid re-querying on every\
	 * page load.
	 *
	 * @param {string} targetLang The target language code.
	 * @return {Array<string,Array<string>>}
	 */
	function getSupportedLangs( targetLang ) {
		const localStorageKey = 'ext-CommunityRequests-langlist-' + targetLang;
		const stored = mwStorage.local.get( localStorageKey );
		if ( stored ) {
			return Promise.resolve( JSON.parse( stored ) );
		}
		const url = 'https://cxserver.wikimedia.org/v1/list/mt';
		return fetch( url ).then( ( response ) => response.text().then( ( body ) => {
			const sourceLangs = [];
			try {
				const mintLangs = JSON.parse( body ).MinT;
				// The API maps each language to those that it can be translated to,
				// but we want a list of all possible source langs for our target.
				if ( mintLangs[ targetLang ] ) {
					for ( const sourceLang of Object.keys( mintLangs ) ) {
						if ( mintLangs[ sourceLang ].indexOf( targetLang ) !== -1 ) {
							sourceLangs.push( sourceLang );
						}
					}
				}
			} catch ( e ) {
				// Unable to parse response.
			}
			// Store for 24 hours.
			mwStorage.local.set( localStorageKey, JSON.stringify( sourceLangs ), 60 * 60 * 24 );
			return sourceLangs;
		} ) );
	}

	/**
	 * Get all DOM nodes that need to be translated.
	 *
	 * @todo More needs to be done here to select nodes and/or elements that are
	 * actually needing to be translated and that are of the most appropriate size
	 * and scope. Probably we should be collecting elements and not leaf nodes, but
	 * if we do that then in many cases we end up also having translations inside
	 * them, so more work is needed there.
	 *
	 * @param {Element} content DOM containing at least one .mw-parser-output element.
	 * @param {string} targetLang
	 * @return {Promise<Array<Node>>}
	 */
	function getTranslatableNodes( content, targetLang ) {
		const parserOutput = content.querySelector( '.mw-parser-output' );
		if ( parserOutput === null ) {
			return Promise.resolve( [] );
		}

		return getSupportedLangs( targetLang ).then( ( supportedLangs ) => {
			// Find all text nodes that are in a different language to the interface language.
			const walker = document.createTreeWalker(
				parserOutput,
				NodeFilter.SHOW_TEXT,
				( node ) => {
					// Skip empty nodes, and everything in the <languages /> bar.
					if ( node.nodeValue.trim() === '' ||
						node.parentElement.closest( '.mw-pt-languages' )
					) {
						return NodeFilter.FILTER_SKIP;
					}
					const lang = node.parentElement.closest( '[lang]' ).lang;
					// Skip if they're the same language.
					if ( lang === targetLang ||
						// Skip style elements.
						node.parentElement instanceof HTMLStyleElement ||
						// Skip if any parent has `.translate-no`. T161486.
						// @todo Fix this to permit `.translate-yes` to be inside a `.translate-no`.
						node.parentElement.closest( '.translate-no' )
					) {
						return NodeFilter.FILTER_SKIP;
					}
					// Check if the source lang can be translated to the target lang.
					if ( supportedLangs.indexOf( lang ) === -1 ) {
						return NodeFilter.FILTER_SKIP;
					}
					// Save the parent lang on the node
					// for easier access when sending it for translation.
					node.lang = lang;
					return NodeFilter.FILTER_ACCEPT;
				}
			);

			// Get all nodes.
			let n = walker.nextNode();
			const translatableNodes = [];
			while ( n ) {
				translatableNodes.push( n );
				n = walker.nextNode();
			}
			return translatableNodes;
		} );
	}
}() );
