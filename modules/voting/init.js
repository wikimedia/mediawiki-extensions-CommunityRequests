'use strict';
const buttonRoot = document.getElementById( 'voting-button' );
if ( buttonRoot ) {
	const Vue = require( 'vue' );
	const VotingButton = require( './Button.vue' );
	Vue.createMwApp( VotingButton ).mount( buttonRoot );
}
