<template>
	<cdx-message
		v-if="translatableNodeCount"
		class="ext-communityrequests-mint"
		allow-user-dismiss
		:icon="cdxIconRobot"
	>
		{{ $i18n( 'communityrequests-translation-translatable', userLanguageName ).text() }}
		<p>
			<cdx-toggle-switch
				v-model="enabled"
				@update:model-value="onToggle"
			>
				<!-- eslint-disable vue/no-v-html -->
				<span v-html="$i18n( 'communityrequests-translation-switch' ).parse()">
				</span>
			</cdx-toggle-switch>
		</p>
		<div v-if="enabled && inProgress">
			<cdx-progress-bar aria-hidden="true"></cdx-progress-bar>
			{{ $i18n( 'communityrequests-translation-progress' )
				.params( [ translatedNodeCount, translatableNodeCount ] )
				.text() }}
		</div>
		<div v-if="enabled && errors.length > 0" class="wishlist-translation-errors">
			<strong>{{ $i18n( 'communityrequests-translation-errors' ).text() }}</strong>
			<ul>
				<li v-for="error in errors" :key="error">
					{{ error }}
				</li>
			</ul>
		</div>
	</cdx-message>
</template>

<script>
const { ref, computed, defineComponent, onMounted } = require( 'vue' );
const { CdxMessage, CdxProgressBar, CdxToggleSwitch } = require( '../codex.js' );
const { cdxIconRobot } = require( './icons.json' );
const storage = require( 'mediawiki.storage' ).local;
const storageName = 'wishlist-intake-translation-enabled';

module.exports = exports = defineComponent( {
	name: 'TranslationBanner',
	components: {
		CdxMessage,
		CdxProgressBar,
		CdxToggleSwitch
	},
	props: {
		targetLang: { type: String, default: '' },
		targetLangDir: { type: String, default: 'ltr' }
	},
	setup( props ) {
		// Reactive properties

		const enabled = ref( storage.get( storageName ) === '1' );
		const inProgress = ref( 0 );
		const translatableNodes = ref( [] );
		const translatedNodeCount = ref( 0 );
		const errors = ref( [] );

		// Computed properties

		const translatableNodeCount = computed( () => translatableNodes.value.length );
		const userLanguageName = computed( () => {
			const langNames = mw.language.getData( props.targetLang, 'languageNames' );
			if ( langNames && langNames[ props.targetLang ] !== undefined ) {
				return langNames[ props.targetLang ];
			}
			return props.targetLang;
		} );

		// Functions

		async function onToggle() {
			storage.set( storageName, enabled.value ? '1' : '0' );
			for ( const node of translatableNodes.value ) {
				if ( !node.isConnected ) {
					// May have been removed since being queried in init.js
					continue;
				}
				if ( !enabled.value ) {
					// Disable by returning to untranslated values.
					node.nodeValue = node.nodeValueUntranslated;
					node.parentElement.lang = node.langOriginal;
					node.parentElement.dir = node.dirOriginal;
					continue;
				}

				if ( node.nodeValueTranslated !== undefined ) {
					// If this node has been translated already, switch to that value.
					node.nodeValue = node.nodeValueTranslated;
					node.parentElement.lang = props.targetLang;
					node.parentElement.dir = props.targetLangDir;
				} else {
					// Otherwise, get the translation.
					node.nodeValueUntranslated = node.nodeValue;
					node.parentElement.style.opacity = '0.6';
					inProgress.value++;
					// Note that node.lang has been set in the init script.
					const translatedHtml = await getTranslation(
						node.nodeValueUntranslated,
						node.lang
					);
					node.parentElement.style.opacity = '';
					inProgress.value--;
					node.langOriginal = node.lang;
					node.dirOriginal = node.dir;
					if ( translatedHtml === '' ) {
						return;
					}
					node.parentElement.lang = props.targetLang;
					node.parentElement.dir = props.targetLangDir;
					translatedNodeCount.value++;
					node.nodeValueTranslated = translatedHtml;
					node.nodeValue = node.nodeValueTranslated;
				}
			}
		}

		/**
		 * @param {string} html
		 * @param {string} srcLang
		 * @return {Promise<string>}
		 */
		async function getTranslation( html, srcLang ) {
			const url = `https://cxserver.wikimedia.org/v1/mt/${ srcLang }/${ props.targetLang }/MinT`;
			const response = await fetch( url, {
				method: 'POST',
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json'
				},
				body: JSON.stringify( { html: html } )
			} );
			const body = await response.text();

			// It is not always JSON that is returned. T373418.
			// @todo i18n for error messages
			let responseBody = '';
			try {
				responseBody = JSON.parse( body );
			} catch ( e ) {
				errors.value.push( 'Unable to decode MinT response: ' + body );
				return '';
			}
			if ( !responseBody.contents ) {
				errors.value.push( 'No MinT response contents. Response was: ' + body );
				return '';
			}

			// Wrap output with spaces if the input was (MinT strips them).
			return ( html.startsWith( ' ' ) ? ' ' : '' ) +
				responseBody.contents +
				( html.endsWith( ' ' ) ? ' ' : '' );
		}

		/**
		 * Process all DOM nodes that need to be translated.
		 *
		 * @todo More needs to be done here to select nodes and/or elements that are
		 * actually needing to be translated and that are of the most appropriate size
		 * and scope. Probably we should be collecting elements and not leaf nodes, but
		 * if we do that then in many cases we end up also having translations inside
		 * them, so more work is needed there.
		 *
		 * @param {Element} content DOM containing at least one .mw-parser-output element.
		 * @param {string} targetLang
		 */
		async function setTranslatableNodes( content, targetLang ) {
			const parserOutput = content.querySelector( '.mw-parser-output' );
			if ( parserOutput === null ) {
				return;
			}

			const supportedLangs = await getSupportedLangs( targetLang );
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
					if ( !supportedLangs.includes( lang ) ) {
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
			const nodes = [];
			while ( n ) {
				nodes.push( n );
				n = walker.nextNode();
			}
			translatableNodes.value = nodes;
		}

		/**
		 * Get all source languages supported by MinT for the given target language,
		 * caching the result in localStorage for a day to avoid re-querying on every
		 * page load.
		 *
		 * @param {string} targetLang The target language code.
		 * @return {Array<string,Array<string>>}
		 */
		async function getSupportedLangs( targetLang ) {
			const localStorageKey = 'ext-CommunityRequests-langlist-' + targetLang;
			const stored = storage.get( localStorageKey );
			if ( stored ) {
				return JSON.parse( stored );
			}
			const url = 'https://cxserver.wikimedia.org/v1/list/mt';
			const response = await fetch( url );
			const body = await response.text();
			const sourceLangs = [];
			try {
				const mintLangs = JSON.parse( body ).MinT;
				// The API maps each language to those that it can be translated to,
				// but we want a list of all possible source langs for our target.
				if ( mintLangs[ targetLang ] ) {
					for ( const sourceLang of Object.keys( mintLangs ) ) {
						if ( mintLangs[ sourceLang ].includes( targetLang ) ) {
							sourceLangs.push( sourceLang );
						}
					}
				}
			} catch ( e ) {
				// Unable to parse response.
			}
			// Store for 24 hours.
			storage.set( localStorageKey, JSON.stringify( sourceLangs ), 60 * 60 * 24 );
			return sourceLangs;
		}

		// Lifecycle hooks

		onMounted( () => {
			mw.hook( 'wikipage.content' ).add( async ( $content ) => {
				await setTranslatableNodes( $content[ 0 ], props.targetLang );

				if ( enabled.value ) {
					await onToggle();
				}
			} );
		} );

		return {
			cdxIconRobot,
			enabled,
			inProgress,
			translatedNodeCount,
			translatableNodeCount,
			errors,
			userLanguageName,
			onToggle
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-communityrequests-mint {
	margin-top: @spacing-50;
}
</style>
