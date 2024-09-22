const Util = require( '../../../modules/common/Util.js' );
const wgConfig = require( '../../../modules/common/config.json' );

const testCases = [
	{
		title: 'creating a new wish',
		config: {
			wgAction: 'view',
			wgPageName: 'Special:WishlistIntake',
			wgCanonicalSpecialPageName: 'WishlistIntake',
			paramValue: null,
			intakeWishTitle: null
		},
		expectations: {
			isNewWish: true,
			isWishView: false,
			isWishEdit: false,
			isManualWishEdit: false,
			isWishRelatedPage: true,
			shouldShowForm: true,
			slug: ''
		}
	},
	{
		title: 'viewing a wish',
		config: {
			wgAction: 'view',
			wgPageName: wgConfig.CommunityRequestsWishPagePrefix + 'Example_wish',
			wgCategories: [ wgConfig.CommunityRequestsWishCategory ],
			paramValue: null
		},
		expectations: {
			isNewWish: false,
			isWishView: true,
			isWishEdit: false,
			isManualWishEdit: false,
			isWishRelatedPage: true,
			shouldShowForm: false,
			slug: 'Example wish'
		}
	},
	{
		title: 'editing a wish',
		config: {
			wgAction: 'view',
			wgPageName: 'Special:WishlistIntake/Example_wish',
			wgCanonicalSpecialPageName: 'WishlistIntake',
			wgCategories: [ 'Community Wishlist/Wishes' ],
			paramValue: '1',
			intakeWishTitle: 'Example wish'
		},
		expectations: {
			isNewWish: false,
			isWishView: false,
			isWishEdit: true,
			isManualWishEdit: false,
			isWishRelatedPage: true,
			shouldShowForm: true,
			slug: 'Example wish'
		}
	},
	{
		title: 'manually editing a wish',
		config: {
			wgAction: 'edit',
			wgPageName: wgConfig.CommunityRequestsWishPagePrefix + 'Example_wish',
			wgCanonicalSpecialPageName: false,
			wgCategories: [],
			paramValue: null
		},
		expectations: {
			isNewWish: false,
			isWishView: false,
			isWishEdit: false,
			isManualWishEdit: true,
			isWishRelatedPage: true,
			shouldShowForm: false,
			slug: 'Example wish'
		}
	}
];

describe( 'Util', () => {
	it.each( testCases )(
		'Util ($title)',
		( { config, expectations } ) => {
			// Mock known mw function calls.
			mw.config.get = jest.fn().mockImplementation( ( key ) => {
				// Check key against a list that we know are properly mocked.
				const expectedKeys = [
					'wgPageName',
					'wgCanonicalSpecialPageName',
					'wgCategories',
					'wgAction',
					'intakeWishTitle'
				];
				if ( !expectedKeys.includes( key ) ) {
					throw new Error( 'Unexpected key: ' + key );
				}
				return config[ key ];
			} );
			mw.util.getParamValue = jest.fn().mockImplementation( () => config.paramValue );

			// Assert expectations.
			expect( Util.isNewWish() ).toStrictEqual( expectations.isNewWish );
			expect( Util.isWishView() ).toStrictEqual( expectations.isWishView );
			expect( Util.isWishEdit() ).toStrictEqual( expectations.isWishEdit );
			expect( Util.isManualWishEdit() ).toStrictEqual( expectations.isManualWishEdit );
			// expect( Util.getWishSlug() ).toStrictEqual( expectations.slug );
		}
	);
} );
