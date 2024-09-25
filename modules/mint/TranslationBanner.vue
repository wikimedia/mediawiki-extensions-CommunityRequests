<template>
	<!-- eslint-disable vue/no-v-html -->
	<cdx-message allow-user-dismiss :icon="cdxIconRobot">
		<div
			v-html="$i18n(
				'communityrequests-translation-translatable', userLanguageName
			).parse()">
		</div>
		<p>
			<cdx-toggle-switch
				v-model="enabled"
				@update:model-value="onToggle"
			>
				<span v-html="$i18n( 'communityrequests-translation-switch' ).parse()">
				</span>
			</cdx-toggle-switch>
		</p>
		<div v-if="enabled && inprogress">
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
const { CdxMessage, CdxProgressBar, CdxToggleSwitch } = require( '@wikimedia/codex' );
const { cdxIconRobot } = require( './icons.json' );

// @vue/component
module.exports = exports = {
	name: 'TranslationBanner',
	components: {
		CdxMessage,
		CdxProgressBar,
		CdxToggleSwitch
	},
	props: {
		translatableNodes: { type: Array, default: () => [] },
		targetLang: { type: String, default: '' },
		targetLangDir: { type: String, default: 'ltr' }
	},
	data() {
		return {
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
