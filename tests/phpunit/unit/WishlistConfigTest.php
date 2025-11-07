<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Config\ConfigException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Language\LanguageNameUtils;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleParser;
use MediaWikiUnitTestCase;
use MockTitleTrait;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\WishlistConfig
 */
class WishlistConfigTest extends MediaWikiUnitTestCase {

	use MockWishlistConfigTrait;
	use MockServiceDependenciesTrait;
	use MockTitleTrait;

	protected WishlistConfig $config;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->getConfig(
			[
				WishlistConfig::WISH_CATEGORY => 'Category:Wishes',
				WishlistConfig::FOCUS_AREA_CATEGORY => 'Category:Focus areas',
				WishlistConfig::TAGS => [
					'navigation' => [
						'admins' => [
							'id' => 0,
							// NOTE: The NS prefix in the value would require mocking NamespaceInfo.
							// AbstractRenderer::addTranslationCategory() will correctly strip
							// the "Category:" prefix that would normally be present here.
							'category' => 'Community Wishlist/Admins and stewards',
						],
						'botsgadgets' => [
							'id' => 1,
							'category' => 'Community Wishlist/Bots and gadgets',
							'label' => 'communityrequests-tag-bots-gadgets',
						],
						'editing' => [
							'id' => 2,
							'category' => 'Community Wishlist/Editing',
						],
					]
				],
				WishlistConfig::STATUSES => [
					'open' => [
						'id' => 0,
					],
					'closed' => [
						'id' => 1,
						'voting' => false,
					],
					'unknown' => [
						'id' => 2,
						'default' => true
					]
				],
			],
		);
	}

	public function testGetters(): void {
		$this->assertTrue( $this->config->isEnabled() );
		$this->assertSame( 'Community Wishlist', $this->config->getHomepage() );
		$this->assertSame( 'Category:Wishes', $this->config->getWishCategory() );
		$this->assertSame( 'Community Wishlist/W', $this->config->getWishPagePrefix() );
		$this->assertSame( 'Community Wishlist/Wishes', $this->config->getWishIndexPage() );
		$this->assertSame( [ 'feature', 'bug', 'change', 'unknown' ], array_keys( $this->config->getWishTypes() ) );
		$this->assertSame( 'Category:Focus areas', $this->config->getFocusAreaCategory() );
		$this->assertSame( 'Community Wishlist/FA', $this->config->getFocusAreaPagePrefix() );
		$this->assertSame( 'Community Wishlist/Focus areas', $this->config->getFocusAreaIndexPage() );
		$this->assertSame( [ 'open', 'closed', 'unknown' ], array_keys( $this->config->getStatuses() ) );
		$this->assertSame( [ 'open', 'closed', 'unknown' ], array_keys( $this->config->getStatuses() ) );
		$this->assertSame( [ 'admins', 'botsgadgets', 'editing' ], array_keys( $this->config->getNavigationTags() ) );
		$this->assertSame( '/Votes', $this->config->getVotesPageSuffix() );
		$this->assertTrue( $this->config->isWishVotingEnabled() );
		$this->assertTrue( $this->config->isFocusAreaVotingEnabled() );
	}

	public function testGetStatusesEligibleForVoting(): void {
		$expected = [
			'open' => [
				'id' => 0,
			],
			'unknown' => [
				'id' => 2,
				'default' => true
			]
		];
		$this->assertSame( $expected, $this->config->getStatusesEligibleForVoting() );
	}

	public function testGetStatusIdsEligibleForVoting(): void {
		$this->assertSame( [ 0, 2 ], $this->config->getStatusIdsEligibleForVoting() );
	}

	public function testGetStatusWikitextValsEligibleForVoting(): void {
		$this->assertSame( [ 'open', 'unknown' ], $this->config->getStatusWikitextValsEligibleForVoting() );
	}

	public function testIsWishOrFocusAreaPage(): void {
		$this->assertTrue( $this->config->isWishOrFocusAreaPage( $this->makeMockTitle( 'Community Wishlist/W123' ) ) );
		$this->assertTrue( $this->config->isWishOrFocusAreaPage( $this->makeMockTitle( 'Community Wishlist/FA123' ) ) );

		$this->assertFalse( $this->config->isWishPage( $this->makeMockTitle( '123' ) ) );
		$this->assertFalse( $this->config->isWishPage( $this->makeMockTitle( 'W123' ) ) );
		$this->assertTrue( $this->config->isWishPage( $this->makeMockTitle( 'Community Wishlist/W123' ) ) );
		$this->assertTrue( $this->config->isWishPage( $this->makeMockTitle( 'Community Wishlist/W123/fr' ) ) );
		$this->assertFalse( $this->config->isWishPage( $this->makeMockTitle( 'Community Wishlist/Wfr' ) ) );
		$this->assertFalse( $this->config->isWishPage( $this->makeMockTitle( 'Community Wishlist/WWWWW123' ) ) );
		$this->assertFalse( $this->config->isWishPage( $this->makeMockTitle( 'Community Wishlist/W123/b-o-g-u-s' ) ) );
		$this->assertFalse( $this->config->isWishPage( $this->makeMockTitle( 'Community Wishlist/W123/fr-fake' ) ) );
		$this->assertFalse( $this->config->isWishPage( $this->makeMockTitle( 'Community Wishlist/Wish', [
			'namespace' => NS_TEMPLATE
		] ) ) );
		$this->assertFalse( $this->config->isWishPage( $this->makeMockTitle( 'Community Wishlist/W123/Votes' ) ) );

		$this->assertFalse( $this->config->isFocusAreaPage( $this->makeMockTitle( 'FA123' ) ) );
		$this->assertTrue( $this->config->isFocusAreaPage( $this->makeMockTitle( 'Community Wishlist/FA123' ) ) );
		$this->assertTrue( $this->config->isFocusAreaPage( $this->makeMockTitle( 'Community Wishlist/FA123/fr' ) ) );
		$this->assertFalse(
			$this->config->isFocusAreaPage( $this->makeMockTitle( 'Community Wishlist/FA123/unrelated-subpage' ) )
		);
	}

	public function testIsWishOrFocusAreaIndexPage(): void {
		$this->assertTrue(
			$this->config->isWishOrFocusAreaIndexPage( $this->makeMockTitle( 'Community Wishlist/Wishes' ) )
		);
		$this->assertTrue(
			$this->config->isWishOrFocusAreaIndexPage( $this->makeMockTitle( 'Community Wishlist/Focus areas' ) )
		);

		$this->assertTrue( $this->config->isWishIndexPage( $this->makeMockTitle( 'Community Wishlist/Wishes' ) ) );
		$this->assertTrue( $this->config->isWishIndexPage( $this->makeMockTitle( 'Community Wishlist/Wishes/fr' ) ) );
		$this->assertFalse(
			$this->config->isWishIndexPage( $this->makeMockTitle( 'Community Wishlist/Wishes/fr-fake' ) )
		);
		$this->assertFalse(
			$this->config->isWishIndexPage( $this->makeMockTitle( 'Community Wishlist/Wishes/b-o-g-u-s' ) )
		);
		$this->assertFalse(
			$this->config->isWishIndexPage( $this->makeMockTitle( 'Community Wishlist/W123/Wishes' ) )
		);
		$this->assertTrue(
			$this->config->isFocusAreaIndexPage( $this->makeMockTitle( 'Community Wishlist/Focus areas' ) )
		);
		$this->assertTrue(
			$this->config->isFocusAreaIndexPage( $this->makeMockTitle( 'Community Wishlist/Focus areas/fr' ) )
		);
		$this->assertFalse(
			$this->config->isFocusAreaIndexPage( $this->makeMockTitle( 'Community Wishlist/Focus areas/fr-fake' ) )
		);
		$this->assertFalse(
			$this->config->isFocusAreaIndexPage( $this->makeMockTitle( 'Community Wishlist/Focus areas/b-o-g-u-s' ) )
		);
		$this->assertFalse(
			$this->config->isFocusAreaIndexPage( $this->makeMockTitle( 'Community Wishlist/FA123/Focus areas' ) )
		);
	}

	public function testGetEntityPageRefFromVotesPage(): void {
		$this->assertSame(
			$this->makeMockTitle( 'Community Wishlist/W123' )->getDBkey(),
			$this->config->getEntityPageRefFromVotesPage(
				$this->makeMockTitle( 'Community Wishlist/W123/Votes' )
			)->getDBkey()
		);
		$this->assertNull(
			$this->config->getEntityPageRefFromVotesPage(
				$this->makeMockTitle( 'Community Wishlist/W123/b-o-g-u-s' )
			)
		);
	}

	public function testGetWishTypeIdFromWikitextVal(): void {
		$this->assertSame( 0, $this->config->getWishTypeIdFromWikitextVal( 'feature' ) );
		$this->assertSame( 1, $this->config->getWishTypeIdFromWikitextVal( 'bug' ) );
		$this->assertSame( 2, $this->config->getStatusIdFromWikitextVal( 'unknown' ) );
		$this->expectException( ConfigException::class );
		$this->assertSame( 2, $this->config->getWishTypeIdFromWikitextVal( 'bogus' ) );
	}

	public function testGetWishTypeLabelFromWikitextVal(): void {
		$this->assertSame( 'communityrequests-wishtype-feature',
			$this->config->getWishTypeLabelFromWikitextVal( 'feature' )
		);
		$this->assertSame( 'communityrequests-wishtype-bug', $this->config->getWishTypeLabelFromWikitextVal( 'bug' ) );
		$this->assertNull( $this->config->getWishTypeLabelFromWikitextVal( 'bogus' ) );
	}

	public function testGetTagIdFromWikitextVal(): void {
		$this->assertSame( 0, $this->config->getTagIdFromWikitextVal( 'admins' ) );
		$this->assertSame( 1, $this->config->getTagIdFromWikitextVal( 'botsgadgets' ) );
		$this->assertNull( $this->config->getTagIdFromWikitextVal( 'bogus' ) );
	}

	public function testGetTagLabelFromWikitextVal(): void {
		$this->assertSame( 'communityrequests-tag-admins', $this->config->getTagLabelFromWikitextVal( 'admins' ) );
		$this->assertSame(
			'communityrequests-tag-bots-gadgets',
			$this->config->getTagLabelFromWikitextVal( 'botsgadgets' )
		);
		$this->assertNull( $this->config->getTagLabelFromWikitextVal( 'bogus' ) );
	}

	public function testGetTagCategoryFromWikitextVal(): void {
		$this->assertSame(
			'Community_Wishlist/Admins_and_stewards',
			$this->config->getTagCategoryFromWikitextVal( 'admins' )
		);
	}

	public function testGetStatusIdFromWikitextVal(): void {
		$this->assertSame( 0, $this->config->getStatusIdFromWikitextVal( 'open' ) );
		$this->assertSame( 1, $this->config->getStatusIdFromWikitextVal( 'closed' ) );
		$this->assertSame( 2, $this->config->getStatusIdFromWikitextVal( 'unknown' ) );
		$this->assertNull( $this->config->getStatusIdFromWikitextVal( 'bogus' ) );
	}

	public function testGetStatusLabelFromWikitextVal(): void {
		$this->assertSame(
			'communityrequests-status-wish-open',
			$this->config->getStatusLabelFromWikitextVal( 'wish', 'open' )
		);
		$this->assertSame(
			'communityrequests-status-wish-closed',
			$this->config->getStatusLabelFromWikitextVal( 'wish', 'closed' )
		);
		$this->assertSame(
			'communityrequests-status-focus-area-unknown',
			$this->config->getStatusLabelFromWikitextVal( 'focus-area', 'unknown' )
		);
		$this->assertNull( $this->config->getStatusLabelFromWikitextVal( 'wish', 'bogus' ) );
	}

	/**
	 * @dataProvider provideGetDefaultStatusWikitextVal
	 */
	public function testGetDefaultStatusWikitextVal( array $statuses, string $expected ) {
		$config = new WishlistConfig(
			new ServiceOptions( WishlistConfig::CONSTRUCTOR_OPTIONS, [
				WishlistConfig::ENABLED => true,
				WishlistConfig::HOMEPAGE => '',
				WishlistConfig::WISH_CATEGORY => '',
				WishlistConfig::WISH_PAGE_PREFIX => '',
				WishlistConfig::WISH_INDEX_PAGE => '',
				WishlistConfig::WISH_TYPES => [],
				WishlistConfig::FOCUS_AREA_CATEGORY => '',
				WishlistConfig::FOCUS_AREA_PAGE_PREFIX => '',
				WishlistConfig::FOCUS_AREA_INDEX_PAGE => '',
				WishlistConfig::TAGS => null,
				WishlistConfig::STATUSES => $statuses,
				WishlistConfig::VOTES_PAGE_SUFFIX => '',
				WishlistConfig::WISH_VOTING_ENABLED => true,
				WishlistConfig::FOCUS_AREA_VOTING_ENABLED => true,
				MainConfigNames::LanguageCode => '',
			] ),
			$this->createMock( TitleParser::class ),
			$this->createMock( TitleFormatter::class ),
			$this->createMock( LanguageNameUtils::class )
		);
		$this->assertSame( $expected, $config->getDefaultStatusWikitextVal() );
	}

	public static function provideGetDefaultStatusWikitextVal(): array {
		return [
			'one status, no default' => [
				[
					'onestatus' => [ 'id' => 0 ]
				],
				'onestatus',
			],
			'multiple statuses, 2nd is default' => [
				[
					'onestatus' => [ 'id' => 2 ],
					'twostatus' => [ 'id' => 4, 'default' => true ],
					'threestatus' => [ 'id' => 0 ],
				],
				'twostatus',
			],
		];
	}

	public function testGetEntityWikitextVal(): void {
		$this->assertSame( 'FA123',
			$this->config->getEntityWikitextVal( $this->makeMockTitle( 'Community Wishlist/FA123' ) )
		);
		$this->assertSame(
			'W123',
			$this->config->getEntityWikitextVal( $this->makeMockTitle( 'Community Wishlist/W123/fr' ) )
		);
		$this->assertNull(
			$this->config->getEntityWikitextVal( $this->makeMockTitle( 'Community Wishlist/W123/b-o-g-u-s' ) )
		);
		$this->assertNull(
			$this->config->getEntityWikitextVal( $this->makeMockTitle( 'Community Wishlist/W123/Votes' ) )
		);
		$this->assertNull( $this->config->getEntityWikitextVal( $this->makeMockTitle( 'Bogus' ) ) );
	}

	public function testGetEntityPageRefFromWikitextVal(): void {
		$this->assertSame(
			'Community_Wishlist/W123',
			$this->config->getEntityPageRefFromWikitextVal( 'W123' )->getDBkey()
		);
		$this->assertSame(
			'Community_Wishlist/FA123',
			$this->config->getEntityPageRefFromWikitextVal( ' FA123 ' )->getDBkey()
		);
		$this->assertSame(
			'Community_Wishlist/W123',
			$this->config->getEntityPageRefFromWikitextVal( 'Community_Wishlist/W123' )->getDBkey()
		);
		$this->assertSame(
			'Community_Wishlist/FA123',
			$this->config->getEntityPageRefFromWikitextVal( 'Community_Wishlist/FA123' )->getDBkey()
		);
		$this->assertNull( $this->config->getEntityPageRefFromWikitextVal( 'bogus' ) );
		$this->assertNull( $this->config->getEntityPageRefFromWikitextVal( 'Community_Wishlist/WWW123' ) );
		$this->assertNull( $this->config->getEntityPageRefFromWikitextVal( 'Community_Wishlist/FA' ) );
		$this->assertNull( $this->config->getEntityPageRefFromWikitextVal( 'Community_Wishlist/123' ) );
		$this->assertNull( $this->config->getEntityPageRefFromWikitextVal( '123' ) );
		$this->assertNull( $this->config->getEntityPageRefFromWikitextVal( '' ) );
	}

	public function testGetVotesPageRefForEntity(): void {
		$this->assertSame(
			'Community_Wishlist/W123/Votes',
			$this->config->getVotesPageRefForEntity(
				$this->makeMockTitle( 'Community Wishlist/W123' )
			)->getDBkey()
		);
		$this->assertSame(
			'Community_Wishlist/FA123/Votes',
			$this->config->getVotesPageRefForEntity(
				$this->makeMockTitle( 'Community Wishlist/FA123' )
			)->getDBkey()
		);
		$this->assertSame(
			'Community_Wishlist/W123/Votes',
			$this->config->getVotesPageRefForEntity(
				$this->makeMockTitle( 'Community Wishlist/W123/fr' )
			)->getDBkey()
		);
		$this->assertSame(
			'Community_Wishlist/W123/Votes',
			$this->config->getVotesPageRefForEntity(
				$this->makeMockTitle( 'Community Wishlist/W123/Votes' )
			)->getDBkey()
		);
		$this->assertNull( $this->config->getVotesPageRefForEntity( $this->makeMockTitle( 'Bogus' ) ) );
		$this->assertNull( $this->config->getVotesPageRefForEntity(
			$this->makeMockTitle( 'Community Wishlist/W123/fr-fake' )
		) );
	}

	public function testGetCanonicalEntityPageRef(): void {
		$this->assertSame(
			'Community_Wishlist/W123',
			$this->config->getCanonicalEntityPageRef(
				PageReferenceValue::localReference( NS_MAIN, 'Community Wishlist/W123/fr' )
			)->getDBkey()
		);
		$this->assertSame(
			'Community_Wishlist/W123',
			$this->config->getCanonicalEntityPageRef(
				PageReferenceValue::localReference( NS_MAIN, 'Community Wishlist/W123' )
			)->getDBkey()
		);
		$this->assertSame(
			'Community_Wishlist/FA123',
			$this->config->getCanonicalEntityPageRef(
				PageReferenceValue::localReference( NS_MAIN, 'Community Wishlist/FA123/Votes' )
			)->getDBkey()
		);
		$this->assertNull(
			$this->config->getCanonicalEntityPageRef(
				PageReferenceValue::localReference( NS_MAIN, 'Community Wishlist/W123/fr-fake' )
			)
		);
		$this->assertNull(
			$this->config->getCanonicalEntityPageRef(
				PageReferenceValue::localReference( NS_MAIN, 'Something else/W123/fr' )
			)
		);
		$this->assertNull( $this->config->getCanonicalEntityPageRef( null ) );
	}

	public function testIsVotesPage(): void {
		$this->assertTrue( $this->config->isVotesPage( $this->makeMockTitle( 'Community Wishlist/W123/Votes' ) ) );
		$this->assertTrue( $this->config->isVotesPage( $this->makeMockTitle( 'Community Wishlist/FA123/Votes' ) ) );
		$this->assertFalse( $this->config->isVotesPage( $this->makeMockTitle( 'Community Wishlist/W123/Votes/fr' ) ) );
		$this->assertFalse( $this->config->isVotesPage( $this->makeMockTitle( 'Community Wishlist/W123/fr/Votes' ) ) );
		$this->assertFalse( $this->config->isVotesPage( $this->makeMockTitle( 'Community Wishlist/W123' ) ) );
		$this->assertFalse( $this->config->isVotesPage( $this->makeMockTitle( 'Community Wishlist/Votes' ) ) );
	}
}
