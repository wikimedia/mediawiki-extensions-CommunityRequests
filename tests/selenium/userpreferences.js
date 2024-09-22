'use strict';

const BlankPage = require( 'wdio-mediawiki/BlankPage' ),
	Util = require( 'wdio-mediawiki/Util' );

class UserPreferences {
	async setPreferences( preferences ) {
		await BlankPage.open();
		Util.waitForModuleState( 'mediawiki.base' );
		return await browser.execute( ( prefs ) => mw.loader.using( 'mediawiki.api' ).then(
			() => new mw.Api().saveOptions( prefs )
		), preferences );
	}

	async enableVisualEditor() {
		await this.setPreferences( {
			'visualeditor-enable': '1',
			'visualeditor-newwikitext': '1'
		} );
	}
}

module.exports = new UserPreferences();
