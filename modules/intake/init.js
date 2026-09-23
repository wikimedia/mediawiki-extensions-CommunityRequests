'use strict';

const Util = require( '../common/Util.js' );

let form;
let formData = mw.config.get( 'intakeData' );

if ( Util.isNewWish() || Util.isWishEdit() ) {
	form = require( './SpecialWishlistIntake.vue' );
	const { CommunityRequestsTags } = require( '../common/config.json' );

	const availableTags = Object.keys( CommunityRequestsTags.navigation )
		.map( ( value ) => ( {
			value,
			label: Util.getTagLabel( value ),
			type: CommunityRequestsTags.navigation[ value ].type || 'standard'
		} ) )
		.filter( ( tag ) => tag.label );

	formData = Object.assign( {}, formData, { availableTags } );
} else if ( Util.isNewFocusArea() || Util.isFocusAreaEdit() ) {
	form = require( './SpecialEditFocusArea.vue' );
}

if ( form ) {
	const Vue = require( 'vue' );
	const vm = Vue.createMwApp( form, formData );
	vm.mount( '.ext-communityrequests-intake' );
}
