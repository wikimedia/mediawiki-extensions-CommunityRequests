<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Config\ConfigException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWikiUnitTestCase;

/**
 * @group CommunityRequests
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\WishlistConfig
 */
class WishlistConfigTest extends MediaWikiUnitTestCase {

	protected WishlistConfig $config;

	protected function setUp(): void {
		parent::setUp();
		$this->config = new WishlistConfig( new ServiceOptions( WishlistConfig::CONSTRUCTOR_OPTIONS, [
			WishlistConfig::CONFIG_ENABLED => true,
			WishlistConfig::CONFIG_HOMEPAGE => 'Community Wishlist',
			WishlistConfig::CONFIG_WISH_CATEGORY => 'Category:Wishes',
			WishlistConfig::CONFIG_WISH_PAGE_PREFIX => 'Community Wishlist/W',
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
		] ) );
	}

	/**
	 * @covers ::isEnabled
	 * @covers ::getHomepage
	 * @covers ::getWishCategory
	 * @covers ::getWishPagePrefix
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
		$this->assertSame( [ 'open', 'closed', 'unknown' ], array_keys( $this->config->getStatuses() ) );
		$this->assertSame( [ 'feature', 'bug' ], array_keys( $this->config->getWishTypes() ) );
		$this->assertSame( [ 'wikipedia', 'wikisource' ], array_keys( $this->config->getProjects() ) );
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
	 * @covers ::getProjectIdFromWikitextVal
	 */
	public function testGetProjectIdFromWikitextVal(): void {
		$this->assertSame( 0, $this->config->getProjectIdFromWikitextVal( 'wikipedia' ) );
		$this->assertSame( 1, $this->config->getProjectIdFromWikitextVal( 'wikisource' ) );
		$this->assertNull( $this->config->getProjectIdFromWikitextVal( 'bogus' ) );
	}
}
