'use strict';

const root = document.querySelector( '.ext-communityrequests-wishes' );
if ( root ) {
	const Vue = require( 'vue' );
	const WishIndex = require( './WishIndex.vue' );
	Vue.createMwApp( WishIndex ).mount( root );
}
