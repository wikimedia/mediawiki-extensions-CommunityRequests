<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;
use MockTitleTrait;

/**
 * @group CommunityRequests
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\Wish\Wish
 */
class WishTest extends MediaWikiUnitTestCase {
	use MockTitleTrait;
	use MockAuthorityTrait;

	protected WishlistConfig $config;

	protected function setUp(): void {
		parent::setUp();
		$serviceOptions = new ServiceOptions( WishlistConfig::CONSTRUCTOR_OPTIONS, [
			WishlistConfig::CONFIG_ENABLED => true,
			WishlistConfig::CONFIG_HOMEPAGE => '',
			WishlistConfig::CONFIG_WISH_CATEGORY => '',
			WishlistConfig::CONFIG_WISH_PAGE_PREFIX => '',
			WishlistConfig::CONFIG_FOCUS_AREA_PAGE_PREFIX => '',
			WishlistConfig::CONFIG_WISH_INDEX_PAGE => '',
			WishlistConfig::CONFIG_WISH_TEMPLATE => [
				'page' => 'Template:Wish',
				'params' => [
					'status' => 'status',
					'type' => 'type',
					'title' => 'title',
					'focusArea' => 'focusarea',
					'description' => 'description',
					'audience' => 'audience',
					'projects' => 'projects',
					'otherProject' => 'otherproject',
					'phabTasks' => 'tasks',
					'proposer' => 'proposer',
					'created' => 'created',
				]
			],
			WishlistConfig::CONFIG_WISH_TYPES => [
				'bug' => [ 'id' => 1 ],
				'change' => [ 'id' => 2 ],
			],
			WishlistConfig::CONFIG_PROJECTS => [
				'wikipedia' => [ 'id' => 0 ],
				'wikidata' => [ 'id' => 1 ],
				'commons' => [ 'id' => 2 ],
				'wikisource' => [ 'id' => 3 ],
				'wiktionary' => [ 'id' => 4 ],
				'wikivoyage' => [ 'id' => 5 ],
				'wikiquote' => [ 'id' => 6 ],
				'wikiversity' => [ 'id' => 7 ],
				'wikifunctions' => [ 'id' => 8 ],
				'wikispecies' => [ 'id' => 9 ],
				'wikinews' => [ 'id' => 10 ],
				'metawiki' => [ 'id' => 11 ],
				'wmcs' => [ 'id' => 12 ],
			],
			WishlistConfig::CONFIG_STATUSES => [
				'submitted' => [ 'id' => 1 ],
				'archived' => [ 'id' => 6 ],
			],
		] );
		$this->config = new WishlistConfig( $serviceOptions );
	}

	/**
	 * @covers ::toWikitext
	 * @dataProvider provideToWikitext
	 */
	public function testToWikitext( array $wishData, string $expectedWikitext ): void {
		$wish = $this->getTestWish( $wishData );
		$templateTitleValue = $this->getMockBuilder( TitleValue::class )
			->disableOriginalConstructor()
			->getMock();
		$templateTitleValue->method( 'getText' )
			->willReturn( 'Community Wishlist/Wish' );
		$templateTitleValue->method( 'getNamespace' )
			->willReturn( NS_TEMPLATE );

		$this->assertSame(
			$expectedWikitext,
			$wish->toWikitext( $templateTitleValue, $this->config )->getText()
		);
	}

	/**
	 * @return array[]
	 */
	public static function provideToWikitext(): array {
		return [
			[
				[
					'title' => 'Improve search functionality',
					'status' => 1,
					'description' => 'Make [[search]] results more relevant and faster.',
					'type' => 2,
					'projects' => [ 0, 6, 7 ],
					'otherProject' => 'Offline wikis',
					'audience' => 'Editors and readers',
					'phabTasks' => [ 123, 456 ],
					'created' => '2023-10-01T12:00:00Z',
					'baseLang' => 'en',
				],
				<<<"END"
{{Community Wishlist/Wish
| status = submitted
| type = change
| title = Improve search functionality
| focusarea = \n| description = Make [[search]] results more relevant and faster.
| audience = Editors and readers
| projects = wikipedia,wikiquote,wikiversity
| otherproject = Offline wikis
| tasks = T123,T456
| proposer = Petr
| created = 2023-10-01T12:00:00Z
}}

END
			],
		];
	}

	/**
	 * @covers ::toArray
	 * @dataProvider provideToArray
	 */
	public function testToArray( array $wishData, array $expected ): void {
		$wish = $this->getTestWish( $wishData );
		$this->assertSame( $expected, $wish->toArray( $this->config ) );
	}

	/**
	 * @return array[]
	 */
	public static function provideToArray(): array {
		return [
			[
				[
					'baseLang' => 'en',
					'created' => '2023-10-01T12:00:00Z',
					'otherProject' => 'Offline wikis',
					'phabTasks' => [ 123, 456 ],
					'projects' => [ 0, 6, 7 ],
					'status' => 1,
					'title' => 'Improve search functionality',
					'type' => 2,
				],
				[
					'status' => 'submitted',
					'type' => 'change',
					'title' => 'Improve search functionality',
					'focusArea' => null,
					'description' => '',
					'audience' => '',
					'projects' => [ 'wikipedia', 'wikiquote', 'wikiversity' ],
					'otherProject' => 'Offline wikis',
					'phabTasks' => [ 'T123', 'T456' ],
					'proposer' => 'Petr',
					'created' => '2023-10-01T12:00:00Z',
				],
			],
		];
	}

	/**
	 * @covers ::newFromWikitextParams
	 * @dataProvider provideNewFromWikitextParams
	 */
	public function testNewFromWikitextParams( $wikitextParams, $expected ): void {
		$wish = Wish::newFromWikitextParams(
			$this->makeMockTitle( 'Community Wishlist/Wishes/W123' ),
			'en',
			$this->mockRegisteredUltimateAuthority()->getUser(),
			$wikitextParams,
			$this->config
		);
		$this->assertSame( $expected[ 'title' ], $wish->getTitle() );
		$this->assertSame( $expected[ 'status' ], $wish->getStatus() );
		$this->assertSame( $expected[ 'type' ], $wish->getType() );
		$this->assertSame( $expected[ 'projects' ], $wish->getProjects() );
		$this->assertSame( $expected[ 'otherProject' ], $wish->getOtherProject() );
		$this->assertSame( $expected[ 'phabTasks' ], $wish->getPhabTasks() );
		$this->assertSame( $expected[ 'created' ], $wish->getCreated() );
	}

	public static function provideNewFromWikitextParams(): array {
		return [
			[
				[
					'title' => 'Improve search functionality',
					'status' => 'submitted',
					'description' => 'Make [[search]] results more relevant and faster.',
					'type' => 'change',
					'projects' => 'wikipedia, wikiquote  , wikiversity,bogus',
					'otherProject' => 'Offline wikis',
					'audience' => 'Editors and readers',
					'phabTasks' => 'T123,T456',
					'created' => '2023-10-01T12:00:00Z',
				],
				[
					'title' => 'Improve search functionality',
					'status' => 1,
					'description' => 'Make [[search]] results more relevant and faster.',
					'type' => 2,
					'projects' => [ 0, 6, 7 ],
					'otherProject' => 'Offline wikis',
					'audience' => 'Editors and readers',
					'phabTasks' => [ 123, 456 ],
					'created' => '2023-10-01T12:00:00Z',
				]
			],
			[
				[
					'title' => 'Fix all the bugs ever created',
					'status' => 'archived',
					'description' => 'Fix everything.',
					'type' => 'bug',
					'projects' => 'all',
					'otherProject' => '',
					'audience' => 'Human beings',
					'phabTasks' => '',
					'created' => '2023-10-01T12:00:00Z',
				],
				[
					'title' => 'Fix all the bugs ever created',
					'status' => 6,
					'description' => 'Make [[search]] results more relevant and faster.',
					'type' => 1,
					'projects' => [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 ],
					'otherProject' => null,
					'audience' => 'Editors and readers',
					'phabTasks' => [],
					'created' => '2023-10-01T12:00:00Z',
				]
			]
		];
	}

	/**
	 * @covers ::getProjectsFromCsv
	 */
	public function testGetProjectsFromCsv(): void {
		$this->assertSame( [], Wish::getProjectsFromCsv( '', $this->config ) );
		$this->assertSame(
			[ 0, 2, 11 ],
			Wish::getProjectsFromCsv( 'wikipedia,  commons ,, bogus,metawiki', $this->config )
		);
	}

	/**
	 * @covers ::getPhabTasksFromCsv
	 */
	public function testGetPhabTasksFromCsv(): void {
		$this->assertSame( [], Wish::getPhabTasksFromCsv( '' ) );
		$this->assertSame(
			[ 123, 456, 789 ],
			Wish::getPhabTasksFromCsv( '  T123,456, ,T789,,' )
		);
	}

	private function getTestWish( array $wishData ): Wish {
		return new Wish(
			$this->makeMockTitle( 'Community Wishlist/Wishes/W123' ),
			'en',
			$this->mockRegisteredUltimateAuthority()->getUser(),
			$wishData
		);
	}
}
