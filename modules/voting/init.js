'use strict';
const buttonRoot = document.querySelector( '.ext-communityrequests-voting-btn' );
if ( buttonRoot ) {
	const Vue = require( 'vue' );
	let VotingButton = null;
	if ( mw.user.isNamed() ) {
		VotingButton = require( './Button.vue' );

	} else {
		VotingButton = require( './AuthButton.vue' );
	}
	Vue.createMwApp( VotingButton ).mount( buttonRoot );
}
