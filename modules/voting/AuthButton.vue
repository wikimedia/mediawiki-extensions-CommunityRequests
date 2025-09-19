<template>
	<a
		:href="userLoginUrl"
		class="cdx-button
			cdx-button--fake-button cdx-button--fake-button--enabled
			cdx-button--action-progressive cdx-button--weight-primary"
	>
		{{ Util.isWishPage() ?
			$i18n( 'communityrequests-wish-voting-not-logged-in' ).text() :
			$i18n( 'communityrequests-focus-area-voting-not-logged-in' ).text()
		}}
	</a>
</template>

<script>
const { defineComponent } = require( 'vue' );
const Util = require( '../common/Util.js' );

module.exports = exports = defineComponent( {
	name: 'AuthButton',
	setup() {
		const userLoginUrl = mw.util.getUrl(
			'Special:UserLogin', { returnto: mw.config.get( 'wgPageName' ) }
		);

		return {
			userLoginUrl,
			Util
		};
	}
} );
</script>
