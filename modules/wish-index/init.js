'use strict';

const root = document.querySelector( '.ext-communityrequests-wishes' );
if ( root ) {
	const Vue = require( 'vue' );
	const WishIndexTable = require( './WishIndexTable.vue' );
	Vue.createMwApp( WishIndexTable ).mount( root, mw.config.get( 'wishesData' ) );
}
