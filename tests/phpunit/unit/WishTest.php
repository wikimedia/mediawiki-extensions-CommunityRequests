<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\TitleValue;
use MockTitleTrait;

/**
 * @group CommunityRequests
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\Wish\Wish
 */
class WishTest extends AbstractWishlistEntityTest {
	use MockTitleTrait;
	use MockAuthorityTrait;

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
			$wish->toWikitext( $this->config )->getText()
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
{{#CommunityRequests: wish
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
| baselang = en
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
					'updated' => '2023-10-02T12:00:00Z',
					'otherProject' => 'Offline wikis',
					'phabTasks' => [ 123, 456 ],
					'projects' => [ 0, 6, 7 ],
					'status' => 1,
					'title' => 'Improve search functionality',
					'type' => 2,
					'voteCount' => 3,
				],
				[
					'status' => 'submitted',
					'type' => 'change',
					'title' => 'Improve search functionality',
					'focusArea' => '',
					'description' => null,
					'audience' => '',
					'projects' => [ 'wikipedia', 'wikiquote', 'wikiversity' ],
					'otherProject' => 'Offline wikis',
					'phabTasks' => [ 'T123', 'T456' ],
					'proposer' => 'Petr',
					'voteCount' => 3,
					'created' => '2023-10-01T12:00:00Z',
					'updated' => '2023-10-02T12:00:00Z',
					'baseLang' => 'en',
				],
			],
			[
				[
					'baseLang' => 'en',
					'created' => '2023-10-01T12:00:00Z',
					'otherProject' => null,
					'phabTasks' => [],
					'projects' => [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 ],
					'status' => 6,
					'title' => 'Fix all the bugs ever created',
					'type' => 1,
					'voteCount' => 1,
				],
				[
					'status' => 'archived',
					'type' => 'bug',
					'title' => 'Fix all the bugs ever created',
					'focusArea' => '',
					'description' => null,
					'audience' => '',
					'projects' => [ 'all' ],
					'otherProject' => '',
					'phabTasks' => [],
					'proposer' => 'Petr',
					'voteCount' => 1,
					'created' => '2023-10-01T12:00:00Z',
					'updated' => null,
					'baseLang' => 'en',
				],
			]
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
			$wikitextParams,
			$this->config,
			$this->mockRegisteredUltimateAuthority()->getUser(),
		);
		$this->assertSame( $expected[ 'title' ], $wish->getTitle() );
		$this->assertSame( $expected[ 'status' ], $wish->getStatus() );
		$this->assertSame( $expected[ 'description' ], $wish->getDescription() );
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
					'otherproject' => 'Offline wikis',
					'audience' => 'Editors and readers',
					'phabtasks' => 'T123,T456',
					'created' => '2023-10-01T12:00:00Z',
					'baselang' => 'en',
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
					'baseLang' => 'en',
				]
			],
			[
				[
					'title' => 'Fix all the bugs ever created',
					'status' => 'archived',
					'description' => 'Fix everything.',
					'type' => 'bug',
					'projects' => 'all',
					'otherproject' => '',
					'audience' => 'Human beings',
					'phabtasks' => '',
					'created' => '2023-10-01T12:00:00Z',
					'baselang' => 'en',
				],
				[
					'title' => 'Fix all the bugs ever created',
					'status' => 6,
					'description' => 'Fix everything.',
					'type' => 1,
					'projects' => [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 ],
					'otherProject' => null,
					'audience' => 'Editors and readers',
					'phabTasks' => [],
					'created' => '2023-10-01T12:00:00Z',
					'baseLang' => 'en',
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
		$this->assertSame(
			[ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 ],
			Wish::getProjectsFromCsv( 'all', $this->config )
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
