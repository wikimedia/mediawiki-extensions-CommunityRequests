'use strict';
const buttonRoot = document.querySelector( '.ext-communityrequests-voting-btn' );
if ( buttonRoot ) {
	const Vue = require( 'vue' );
	const VotingButton = require( './Button.vue' );
	Vue.createMwApp( VotingButton ).mount( buttonRoot );
}
