const Util = require( '../../../modules/common/Util.js' );
const { CommunityRequestsWishPagePrefix } = require( '../../../modules/common/config.json' );

const testCases = [
	{
		title: 'creating a new wish',
		config: {
			wgAction: 'view',
			wgPageName: 'Special:WishlistIntake',
			wgCanonicalSpecialPageName: 'WishlistIntake',
			paramValue: null,
			intakeWishId: null
		},
		expectations: {
			isNewWish: true,
			isWishView: false,
			isWishEdit: false,
			isManualWishEdit: false,
			isWishRelatedPage: true
		}
	},
	{
		title: 'viewing a wish',
		config: {
			wgAction: 'view',
			wgPageName: CommunityRequestsWishPagePrefix + 'W123',
			wgCanonicalSpecialPageName: false,
			paramValue: null
		},
		expectations: {
			isNewWish: false,
			isWishView: true,
			isWishEdit: false,
			isManualWishEdit: false,
			isWishRelatedPage: true
		}
	},
	{
		title: 'editing a wish',
		config: {
			wgAction: 'view',
			wgPageName: 'Special:WishlistIntake/W123',
			wgCanonicalSpecialPageName: 'WishlistIntake',
			paramValue: '1',
			intakeWishId: 123
		},
		expectations: {
			isNewWish: false,
			isWishView: false,
			isWishEdit: true,
			isManualWishEdit: false,
			isWishRelatedPage: true
		}
	},
	{
		title: 'manually editing a wish',
		config: {
			wgAction: 'edit',
			wgPageName: CommunityRequestsWishPagePrefix + 'W123',
			wgCanonicalSpecialPageName: false,
			paramValue: null
		},
		expectations: {
			isNewWish: false,
			isWishView: false,
			isWishEdit: false,
			isManualWishEdit: true,
			isWishRelatedPage: true
		}
	}
];

describe( 'Util', () => {
	it.each( testCases )(
		'Util ($title)',
		( { config, expectations } ) => {
			mockMwConfigGet( config );
			mw.util.getParamValue = jest.fn().mockImplementation( () => config.paramValue );

			// Assert expectations.
			expect( Util.isNewWish() ).toStrictEqual( expectations.isNewWish );
			expect( Util.isWishView() ).toStrictEqual( expectations.isWishView );
			expect( Util.isWishEdit() ).toStrictEqual( expectations.isWishEdit );
			expect( Util.isManualWishEdit() ).toStrictEqual( expectations.isManualWishEdit );
		}
	);
} );
