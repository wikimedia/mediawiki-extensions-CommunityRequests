'use strict';

global.mw = require( '@wikimedia/mw-node-qunit/src/mockMediaWiki.js' )();
global.$ = require( 'jquery' );
jest.mock( '../../modules/common/config.json', () => ( {
	CommunityRequestsEnable: true,
	CommunityRequestsHomepage: 'Community_Wishlist',
	CommunityRequestsWishCategory: 'Community Wishlist/Wishes',
	CommunityRequestsWishPagePrefix: 'Community Wishlist/Wishes/',
	CommunityRequestsWishTemplate: 'Community_Wishlist/Wish'
} ), { virtual: true } );
