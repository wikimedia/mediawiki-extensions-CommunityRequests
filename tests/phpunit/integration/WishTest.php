<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Config\ConfigException;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiIntegrationTestCase;
use MockTitleTrait;

/**
 * @group CommunityRequests
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\Wish\Wish
 */
class WishTest extends MediaWikiIntegrationTestCase {
	use MockTitleTrait;
	use MockAuthorityTrait;

	/**
	 * @covers ::toWikitext
	 * @dataProvider provideToWikitext
	 */
	public function testToWikitext( array $wishData, string $expectedWikitext ): void {
		$wish = $this->getTestWish( $wishData );
		$this->assertSame(
			$expectedWikitext,
			$wish->toWikitext(
				$this->getServiceContainer()->getTitleParser()->parseTitle(
					$this->getServiceContainer()->getMainConfig()->get( 'CommunityRequestsWishTemplate' )[ 'page' ]
				),
				$this->getServiceContainer()->getMainConfig()
			)->getText()
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
		$this->assertSame( $expected, $wish->toArray( $this->getServiceContainer()->getMainConfig() ) );
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
			$this->getServiceContainer()->getMainConfig()
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
		$config = $this->getServiceContainer()->getMainConfig();
		$this->assertSame( [], Wish::getProjectsFromCsv( '', $config ) );
		$this->assertSame(
			[ 0, 2, 11 ],
			Wish::getProjectsFromCsv( 'wikipedia,  commons ,, bogus,metawiki', $config )
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

	/**
	 * @covers ::getIdFromWikitextVal
	 */
	public function testGetIdFromWikitextVal(): void {
		$statusConfig = $this->getServiceContainer()->getMainConfig()->get( 'CommunityRequestsStatuses' );
		$this->assertSame( 0, Wish::getIdFromWikitextVal( 'draft', $statusConfig ) );
		$this->assertSame( 1, Wish::getIdFromWikitextVal( '  submitted ', $statusConfig ) );
		$this->assertSame( 0, Wish::getIdFromWikitextVal( 'bogus', $statusConfig ) );
		$this->expectException( ConfigException::class );
		unset( $statusConfig[ 'draft' ] );
		Wish::getIdFromWikitextVal( 'invalid', $statusConfig );

		$typeConfig = $this->getServiceContainer()->getMainConfig()->get( 'CommunityRequestsWishTypes' );
		$this->assertSame( 0, Wish::getIdFromWikitextVal( 'feature', $typeConfig ) );
		$this->assertSame( 1, Wish::getIdFromWikitextVal( '  bug ', $typeConfig ) );
		$this->assertSame( 3, Wish::getIdFromWikitextVal( 'bogus', $typeConfig ) );
		$this->expectException( ConfigException::class );
		unset( $typeConfig[ 'unknown' ] );
		Wish::getIdFromWikitextVal( 'invalid', $typeConfig );
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
