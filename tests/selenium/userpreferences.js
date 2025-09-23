import BlankPage from 'wdio-mediawiki/BlankPage';
import * as Util from 'wdio-mediawiki/Util';

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

export default new UserPreferences();
