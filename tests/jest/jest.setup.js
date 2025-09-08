'use strict';

const vueConfig = require( '@vue/test-utils' ).config;
const mockExtensionJson = require( '../../extension.json' );

global.mw = require( '@wikimedia/mw-node-qunit/src/mockMediaWiki.js' )();
global.$ = require( 'jquery' );
jest.mock( '../../modules/common/config.json', () => {
	const { config } = mockExtensionJson;
	const mappedConfig = {};
	Object.keys( config ).forEach( ( key ) => {
		mappedConfig[ key ] = config[ key ].value;
	} );
	return mappedConfig;
}, { virtual: true } );
mw.confirmCloseWindow = jest.fn();
mw.config.set = jest.fn();

/**
 * Mock for the calls to Core's $i18n plugin which returns a mw.Message object.
 *
 * @param {string} key The key of the message to parse.
 * @param {...*} args Arbitrary number of arguments to be parsed.
 * @return {Object} mw.Message-like object with .text() and .parse() methods.
 */
function $i18nMock( key, ...args ) {
	function serializeArgs() {
		return args.length ? `${ key }:[${ args.join( ',' ) }]` : key;
	}
	return {
		text: () => serializeArgs(),
		parse: () => serializeArgs()
	};
}
// Mock Vue plugins in test suites.
vueConfig.global.provide = {
	i18n: $i18nMock
};
vueConfig.global.mocks = {
	$i18n: $i18nMock
};
vueConfig.global.directives = {
	'i18n-html': ( el, binding ) => {
		el.innerHTML = `${ binding.arg } (${ binding.value })`;
	}
};

/**
 * Mock calls to mw.config.get().
 *
 * @param {Object} [config] Will be merged with the defaults and any previous calls to this function.
 */
function mockMwConfigGet( config = {} ) {
	const mockConfig = Object.assign( {},
		{
			wgUserGroups: [],
			wgUserLanguage: 'en',
			wgUserName: 'ExampleUser',
			wgCanonicalSpecialPageName: false,
			intakeFocusAreas: {},
			intakeId: null,
			intakeTitleMaxChars: 255,
			intakeWishlistManager: false,
			intakeVeModules: [],
			copyrightWarning: 'All contributions <em>are</em>.'
		},
		typeof mw.config.get === 'function' ? mw.config.get() : {},
		config
	);

	mw.config.get = jest.fn().mockImplementation( ( key ) => {
		if ( key === undefined ) {
			// If no key, return all current config values.
			return mockConfig;
		}
		if ( !Object.keys( mockConfig ).includes( key ) ) {
			throw new Error( 'Unexpected key: ' + key );
		}
		return mockConfig[ key ];
	} );
}

global.mockMwConfigGet = mockMwConfigGet;
