'use strict';

const Util = require( '../common/Util.js' );

let form;

if ( Util.isNewWish() || Util.isWishEdit() ) {
	form = require( './SpecialWishlistIntake.vue' );
} else if ( Util.isNewFocusArea() || Util.isFocusAreaEdit() ) {
	form = require( './SpecialEditFocusArea.vue' );
}

if ( form ) {
	const Vue = require( 'vue' );
	const vm = Vue.createMwApp( form, mw.config.get( 'intakeData' ) );
	vm.mount( '.ext-communityrequests-intake' );
}
