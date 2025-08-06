'use strict';

const root = document.querySelector( '.ext-communityrequests-wishes' );
if ( root ) {
	const Vue = require( 'vue' );
	const WishIndexTable = require( './WishIndexTable.vue' );
	Vue.createMwApp( WishIndexTable, mw.config.get( 'wishesData' ) ).mount( root );
}
