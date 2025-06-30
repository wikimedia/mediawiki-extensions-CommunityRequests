<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Config\ConfigException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleParser;
use MediaWikiUnitTestCase;
use MockTitleTrait;

/**
 * @group CommunityRequests
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\WishlistConfig
 */
class WishlistConfigTest extends MediaWikiUnitTestCase {

	use MockServiceDependenciesTrait;
	use MockTitleTrait;

	protected WishlistConfig $config;

	protected function setUp(): void {
		parent::setUp();
		$this->config = new WishlistConfig(
			new ServiceOptions( WishlistConfig::CONSTRUCTOR_OPTIONS, [
				WishlistConfig::CONFIG_ENABLED => true,
				WishlistConfig::CONFIG_HOMEPAGE => 'Community Wishlist',
				WishlistConfig::CONFIG_WISH_CATEGORY => 'Category:Wishes',
				WishlistConfig::CONFIG_WISH_PAGE_PREFIX => 'Community Wishlist/W',
				WishlistConfig::CONFIG_FOCUS_AREA_PAGE_PREFIX => 'Community Wishlist/FA',
				WishlistConfig::CONFIG_WISH_INDEX_PAGE => 'Community Wishlist/Wishes',
				WishlistConfig::CONFIG_WISH_TEMPLATE => [
					'page' => 'Template:Wish',
					'params' => [ 'title' => 'Title', 'description' => 'Desc' ]
				],
				WishlistConfig::CONFIG_WISH_TYPES => [
					'feature' => [
						'id' => 0,
						'label' => 'type-feature',
					],
					'bug' => [
						'id' => 1,
						'label' => 'type-bug',
					],
				],
				WishlistConfig::CONFIG_PROJECTS => [
					'wikipedia' => [
						'id' => 0,
						'label' => 'project-wikipedia',
					],
					'wikisource' => [
						'id' => 1,
						'label' => 'project-wikisource',
					],
				],
				WishlistConfig::CONFIG_STATUSES => [
					'open' => [
						'id' => 0,
						'label' => 'status-open',
					],
					'closed' => [
						'id' => 1,
						'label' => 'status-closed',
					],
					'unknown' => [
						'id' => 2,
						'label' => 'status-unknown',
						'default' => true
					]
				]
			] ),
			$this->newServiceInstance( TitleParser::class, [ 'localInterwikis' => [] ] ),
			$this->newServiceInstance( TitleFormatter::class, [] )
		);
	}

	/**
	 * @covers ::isEnabled
	 * @covers ::getHomepage
	 * @covers ::getWishCategory
	 * @covers ::getWishPagePrefix
	 * @covers ::getFocusAreaPagePrefix
	 * @covers ::getWishIndexPage
	 * @covers ::getWishTemplate
	 * @covers ::getStatuses
	 * @covers ::getWishTypes
	 * @covers ::getProjects
	 */
	public function testGetters(): void {
		$this->assertTrue( $this->config->isEnabled() );
		$this->assertSame( 'Community Wishlist', $this->config->getHomepage() );
		$this->assertSame( 'Category:Wishes', $this->config->getWishCategory() );
		$this->assertSame( 'Community Wishlist/W', $this->config->getWishPagePrefix() );
		$this->assertSame( 'Community Wishlist/Wishes', $this->config->getWishIndexPage() );
		$this->assertSame( 'Template:Wish', $this->config->getWishTemplate()[ 'page' ] );
		$this->assertSame(
			[ 'title' => 'Title', 'description' => 'Desc' ],
			$this->config->getWishTemplate()[ 'params' ]
		);
		$this->assertSame( 'Template:Wish', $this->config->getWishTemplatePage() );
		$this->assertSame( [ 'title' => 'Title', 'description' => 'Desc' ], $this->config->getWishTemplateParams() );
		$this->assertSame( [ 'feature', 'bug' ], array_keys( $this->config->getWishTypes() ) );
		$this->assertSame( 'Community Wishlist/FA', $this->config->getFocusAreaPagePrefix() );
		$this->assertSame( [ 'open', 'closed', 'unknown' ], array_keys( $this->config->getStatuses() ) );
		$this->assertSame( [ 'wikipedia', 'wikisource' ], array_keys( $this->config->getProjects() ) );
	}

	/**
	 * @covers ::isWishOrFocusAreaPage
	 * @covers ::isWishPage
	 * @covers ::isFocusAreaPage
	 */
	public function testIsWishOrFocusAreaPage(): void {
		$this->assertTrue( $this->config->isWishOrFocusAreaPage( $this->makeMockTitle( 'Community Wishlist/W123' ) ) );
		$this->assertTrue( $this->config->isWishOrFocusAreaPage( $this->makeMockTitle( 'Community Wishlist/FA123' ) ) );

		$this->assertFalse( $this->config->isWishPage( $this->makeMockTitle( 'W123' ) ) );
		$this->assertTrue( $this->config->isWishPage( $this->makeMockTitle( 'Community Wishlist/W123' ) ) );
		$this->assertTrue( $this->config->isWishPage( $this->makeMockTitle( 'Community Wishlist/W123/fr' ) ) );
		$this->assertFalse( $this->config->isWishPage( $this->makeMockTitle( 'Community Wishlist/WWWWW123' ) ) );
		$this->assertFalse( $this->config->isWishPage( $this->makeMockTitle( 'Community Wishlist/W123/b-o-g-u-s' ) ) );

		$this->assertFalse( $this->config->isFocusAreaPage( $this->makeMockTitle( 'FA123' ) ) );
		$this->assertTrue( $this->config->isFocusAreaPage( $this->makeMockTitle( 'Community Wishlist/FA123' ) ) );
		$this->assertTrue( $this->config->isFocusAreaPage( $this->makeMockTitle( 'Community Wishlist/FA123/fr' ) ) );
		$this->assertFalse(
			$this->config->isFocusAreaPage( $this->makeMockTitle( 'Community Wishlist/FA123/unrelated-subpage' ) )
		);
	}

	/**
	 * @covers ::getWishTypeIdFromWikitextVal
	 */
	public function testGetWishTypeIdFromWikitextVal(): void {
		$this->assertSame( 0, $this->config->getWishTypeIdFromWikitextVal( 'feature' ) );
		$this->assertSame( 1, $this->config->getWishTypeIdFromWikitextVal( 'bug' ) );
		$this->assertSame( 2, $this->config->getStatusIdFromWikitextVal( 'unknown' ) );
		$this->expectException( ConfigException::class );
		$this->assertSame( 2, $this->config->getWishTypeIdFromWikitextVal( 'bogus' ) );
	}

	/**
	 * @covers ::getWishTypeLabelFromWikitextVal
	 */
	public function testGetWishTypeLabelFromWikitextVal(): void {
		$this->assertSame( 'type-feature', $this->config->getWishTypeLabelFromWikitextVal( 'feature' ) );
		$this->assertSame( 'type-bug', $this->config->getWishTypeLabelFromWikitextVal( 'bug' ) );
		$this->assertNull( $this->config->getWishTypeLabelFromWikitextVal( 'bogus' ) );
	}

	/**
	 * @covers ::getProjectIdFromWikitextVal
	 */
	public function testGetProjectIdFromWikitextVal(): void {
		$this->assertSame( 0, $this->config->getProjectIdFromWikitextVal( 'wikipedia' ) );
		$this->assertSame( 1, $this->config->getProjectIdFromWikitextVal( 'wikisource' ) );
		$this->assertNull( $this->config->getProjectIdFromWikitextVal( 'bogus' ) );
	}

	/**
	 * @covers ::getProjectLabelFromWikitextVal
	 */
	public function testGetProjectLabelFromWikitextVal(): void {
		$this->assertSame( 'project-wikipedia', $this->config->getProjectLabelFromWikitextVal( 'wikipedia' ) );
		$this->assertSame( 'project-wikisource', $this->config->getProjectLabelFromWikitextVal( 'wikisource' ) );
		$this->assertNull( $this->config->getProjectLabelFromWikitextVal( 'bogus' ) );
	}

	/**
	 * @covers ::getStatusIdFromWikitextVal
	 */
	public function testGetStatusIdFromWikitextVal(): void {
		$this->assertSame( 0, $this->config->getStatusIdFromWikitextVal( 'open' ) );
		$this->assertSame( 1, $this->config->getStatusIdFromWikitextVal( 'closed' ) );
		$this->assertSame( 2, $this->config->getStatusIdFromWikitextVal( 'unknown' ) );
		$this->assertSame( 2, $this->config->getStatusIdFromWikitextVal( 'bogus' ) );
	}

	/**
	 * @covers ::getStatusLabelFromWikitextVal
	 */
	public function testGetStatusLabelFromWikitextVal(): void {
		$this->assertSame( 'status-open', $this->config->getStatusLabelFromWikitextVal( 'open' ) );
		$this->assertSame( 'status-closed', $this->config->getStatusLabelFromWikitextVal( 'closed' ) );
		$this->assertSame( 'status-unknown', $this->config->getStatusLabelFromWikitextVal( 'unknown' ) );
		$this->assertNull( $this->config->getStatusLabelFromWikitextVal( 'bogus' ) );
	}

	/**
	 * @covers ::getProjectsWikitextValsFromIds
	 */
	public function testGetProjectsWikitextValsFromIds(): void {
		$this->assertSame( [ 'all' ], $this->config->getProjectsWikitextValsFromIds( [ 0, 1 ] ) );
		$this->assertSame( [ 'wikipedia' ], $this->config->getProjectsWikitextValsFromIds( [ 0 ] ) );
		$this->expectException( ConfigException::class );
		$this->config->getProjectsWikitextValsFromIds( [ 2 ] );
	}
}
