'use strict';

const Vue = require( 'vue' );
const TranslationBanner = require( './TranslationBanner.vue' );

const targetLang = mw.config.get( 'wgUserLanguage' );

// Mount the Vue app.
const appRoot = document.createElement( 'div' );
document.getElementById( 'mw-content-text' ).before( appRoot );
const appData = {
	targetLang,
	// @todo Get the lang dir in a better way.
	targetLangDir: document.querySelector( 'html' ).dir
};
Vue.createMwApp( TranslationBanner, appData ).mount( appRoot );
