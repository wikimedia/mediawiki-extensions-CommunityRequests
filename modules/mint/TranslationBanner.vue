<template>
	<cdx-message allow-user-dismiss :icon="cdxIconRobot">
		<!-- eslint-disable-next-line vue/no-v-html -->
		<p v-html="$i18n( 'communityrequests-translation-translatable', userLanguageName ).parse()">
		</p>
		<p>
			<cdx-toggle-button
				v-model="enabled"
				@update:model-value="onToggle"
			>
				<cdx-icon :icon="cdxIconLanguage"></cdx-icon>
				{{ $i18n( 'communityrequests-translation-show-now' ).text() }}
			</cdx-toggle-button>
		</p>
		<p v-if="enabled && !inprogress">
			{{ $i18n( 'communityrequests-translation-translated', translatedNodeCount ).text() }}
		</p>
		<p v-if="enabled && inprogress">
			<cdx-progress-bar aria-hidden="true"></cdx-progress-bar>
			{{ $i18n( 'communityrequests-translation-progress' )
				.params( [ translatedNodeCount, translatableNodeCount ] )
				.text() }}
		</p>
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
const { CdxIcon, CdxMessage, CdxProgressBar, CdxToggleButton } = require( '@wikimedia/codex' );
const { cdxIconRobot, cdxIconLanguage } = require( './icons.json' );

// @vue/component
module.exports = exports = {
	name: 'TranslationBanner',
	components: {
		CdxIcon,
		CdxMessage,
		CdxProgressBar,
		CdxToggleButton
	},
	props: {
		translatableNodes: { type: Array, default: () => [] },
		targetLang: { type: String, default: '' },
		targetLangDir: { type: String, default: 'ltr' }
	},
	data() {
		return {
			cdxIconLanguage,
			cdxIconRobot,
			enabled: false,
			inprogress: 0,
			translatedNodeCount: 0,
			errors: []
		};
	},
	computed: {
		translatableNodeCount() {
			return this.translatableNodes.length;
		},
		userLanguageName() {
			const langNames = mw.language.getData( this.targetLang, 'languageNames' );
			if ( langNames && langNames[ this.targetLang ] !== undefined ) {
				return langNames[ this.targetLang ];
			}
			// @todo Use ULS data as well.
			return this.targetLang;
		}
	},
	methods: {
		onToggle() {
			for ( const node of this.translatableNodes ) {
				if ( !node.isConnected ) {
					// May have been removed since being queried in wishlistTranslation.init.js
					continue;
				}
				if ( !this.enabled ) {
					// Disable by returning to untranslated values.
					node.nodeValue = node.nodeValueUntranslated;
					node.parentElement.lang = node.langOriginal;
					node.parentElement.dir = node.dirOriginal;
				} else {
					if ( node.nodeValueTranslated !== undefined ) {
						// If this node has been translated already, switch to that value.
						node.nodeValue = node.nodeValueTranslated;
						node.parentElement.lang = this.targetLang;
						node.parentElement.dir = this.targetLangDir;
					} else {
						// Otherwise, get the translation.
						node.nodeValueUntranslated = node.nodeValue;
						node.parentElement.style.opacity = '0.6';
						this.inprogress++;
						// Note that node.lang has been set in the init script.
						this.getTranslation( node.nodeValueUntranslated, node.lang )
							.then( ( translatedHtml ) => {
								node.parentElement.style.opacity = '';
								this.inprogress--;
								node.langOriginal = node.lang;
								node.dirOriginal = node.dir;
								if ( translatedHtml === '' ) {
									return;
								}
								node.parentElement.lang = this.targetLang;
								node.parentElement.dir = this.targetLangDir;
								this.translatedNodeCount++;
								node.nodeValueTranslated = translatedHtml;
								node.nodeValue = node.nodeValueTranslated;
							} );
					}
				}
			}
		},
		/**
		 * @param {string} html
		 * @param {string} srcLang
		 * @return {Promise<string>}
		 */
		getTranslation( html, srcLang ) {
			const url = `https://cxserver.wikimedia.org/v1/mt/${ srcLang }/${ this.targetLang }/MinT`;
			return fetch( url, {
				method: 'POST',
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json'
				},
				body: JSON.stringify( { html: html } )
			} ).then( ( response ) => response.text().then( ( body ) => {
				// It is not always JSON that is returned. T373418.
				// @todo i18n for error messages
				let responseBody = '';
				try {
					responseBody = JSON.parse( body );
				} catch ( e ) {
					this.errors.push( 'Unable to decode MinT response: ' + body );
					return '';
				}
				if ( !responseBody.contents ) {
					this.errors.push( 'No MinT response contents. Response was: ' + body );
					return '';
				}
				// Wrap output with spaces if the input was (MinT strips them).
				return ( html.startsWith( ' ' ) ? ' ' : '' ) +
					responseBody.contents +
					( html.endsWith( ' ' ) ? ' ' : '' );
			} ) );
		}
	}
};
</script>
