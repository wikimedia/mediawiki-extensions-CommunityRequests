'use strict';

const { CommunityRequestsTags } = require( '../common/config.json' );
const Util = require( '../common/Util.js' );

const root = document.querySelector( '.ext-communityrequests-wishes' );
if ( root ) {
	const Vue = require( 'vue' );
	const WishIndex = require( './WishIndex.vue' );

	const availableTags = Object.keys( CommunityRequestsTags.navigation )
		.map( ( value ) => ( {
			value,
			label: Util.getTagLabel( value )
		} ) )
		.filter( ( tag ) => tag.label );
	const wishesData = mw.config.get( 'wishesData' );

	const data = Object.assign( {}, wishesData, { availableTags } );
	Vue.createMwApp( WishIndex, data ).mount( root );
}
