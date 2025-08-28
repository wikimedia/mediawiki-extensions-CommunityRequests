<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MockTitleTrait;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\Wish\Wish
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity
 */
class WishTest extends AbstractWishlistEntityTest {
	use MockTitleTrait;
	use MockAuthorityTrait;

	/**
	 * @dataProvider provideToWikitext
	 */
	public function testToWikitext( array $wishData, string $expectedWikitext ): void {
		$wish = $this->getTestWish( $wishData );
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
					Wish::PARAM_TITLE => 'Improve search functionality',
					Wish::PARAM_STATUS => 1,
					Wish::PARAM_DESCRIPTION => 'Make [[search]] results more relevant and faster.',
					Wish::PARAM_TYPE => 2,
					Wish::PARAM_TAGS => [ 0, 6, 7 ],
					Wish::PARAM_AUDIENCE => 'Editors and readers',
					Wish::PARAM_PHAB_TASKS => [ 123, 456 ],
					Wish::PARAM_CREATED => '2023-10-01T12:00:00Z',
					Wish::PARAM_BASE_LANG => 'en',
				],
				<<<"END"
{{#CommunityRequests: wish
| status = submitted
| type = change
| title = Improve search functionality
| focusarea = \n| description = Make [[search]] results more relevant and faster.
| audience = Editors and readers
| tags = admins,android,mobileweb
| phabtasks = T123,T456
| proposer = Petr
| created = 2023-10-01T12:00:00Z
| baselang = en
}}

END
			],
		];
	}

	/**
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
					Wish::PARAM_BASE_LANG => 'en',
					Wish::PARAM_CREATED => '2023-10-01T12:00:00Z',
					Wish::PARAM_UPDATED => '2023-10-02T12:00:00Z',
					Wish::PARAM_PHAB_TASKS => [ 123, 456 ],
					Wish::PARAM_TAGS => [ 0, 6, 7 ],
					Wish::PARAM_STATUS => 1,
					Wish::PARAM_TITLE => 'Improve search functionality',
					Wish::PARAM_TYPE => 2,
					Wish::PARAM_VOTE_COUNT => 3,
				],
				[
					Wish::PARAM_STATUS => 'submitted',
					Wish::PARAM_TYPE => 'change',
					Wish::PARAM_TITLE => 'Improve search functionality',
					Wish::PARAM_FOCUS_AREA => '',
					Wish::PARAM_DESCRIPTION => null,
					Wish::PARAM_AUDIENCE => '',
					Wish::PARAM_TAGS => [ 'admins', 'android', 'mobileweb' ],
					Wish::PARAM_PHAB_TASKS => [ 'T123', 'T456' ],
					Wish::PARAM_PROPOSER => 'Petr',
					Wish::PARAM_VOTE_COUNT => 3,
					Wish::PARAM_CREATED => '2023-10-01T12:00:00Z',
					Wish::PARAM_UPDATED => '2023-10-02T12:00:00Z',
					Wish::PARAM_BASE_LANG => 'en',
				],
			],
			[
				[
					Wish::PARAM_BASE_LANG => 'en',
					Wish::PARAM_CREATED => '2023-10-01T12:00:00Z',
					Wish::PARAM_PHAB_TASKS => [],
					Wish::PARAM_TAGS => [],
					Wish::PARAM_STATUS => 6,
					Wish::PARAM_TITLE => 'Fix all the bugs ever created',
					Wish::PARAM_TYPE => 1,
					Wish::PARAM_VOTE_COUNT => 1,
				],
				[
					Wish::PARAM_STATUS => 'archived',
					Wish::PARAM_TYPE => 'bug',
					Wish::PARAM_TITLE => 'Fix all the bugs ever created',
					Wish::PARAM_FOCUS_AREA => '',
					Wish::PARAM_DESCRIPTION => null,
					Wish::PARAM_AUDIENCE => '',
					Wish::PARAM_TAGS => [],
					Wish::PARAM_PHAB_TASKS => [],
					Wish::PARAM_PROPOSER => 'Petr',
					Wish::PARAM_VOTE_COUNT => 1,
					Wish::PARAM_CREATED => '2023-10-01T12:00:00Z',
					Wish::PARAM_UPDATED => null,
					Wish::PARAM_BASE_LANG => 'en',
				],
			]
		];
	}

	/**
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
		$this->assertSame( $expected[Wish::PARAM_TITLE], $wish->getTitle() );
		$this->assertSame( $expected[Wish::PARAM_STATUS], $wish->getStatus() );
		$this->assertSame( $expected[Wish::PARAM_DESCRIPTION], $wish->getDescription() );
		$this->assertSame( $expected[Wish::PARAM_TYPE], $wish->getType() );
		$this->assertSame( $expected[Wish::PARAM_TAGS], $wish->getTags() );
		$this->assertSame( $expected[Wish::PARAM_PHAB_TASKS], $wish->getPhabTasks() );
		$this->assertSame( $expected[Wish::PARAM_CREATED], $wish->getCreated() );
	}

	public static function provideNewFromWikitextParams(): array {
		return [
			[
				[
					Wish::PARAM_TITLE => 'Improve search functionality',
					Wish::PARAM_STATUS => 'submitted',
					Wish::PARAM_DESCRIPTION => 'Make [[search]] results more relevant and faster.',
					Wish::PARAM_TYPE => 'change',
					Wish::PARAM_TAGS => 'admins, multimedia  , newcomers,bogus',
					Wish::PARAM_AUDIENCE => 'Editors and readers',
					Wish::PARAM_PHAB_TASKS => 'T123,T456',
					Wish::PARAM_CREATED => '2023-10-01T12:00:00Z',
					Wish::PARAM_BASE_LANG => 'en',
				],
				[
					Wish::PARAM_TITLE => 'Improve search functionality',
					Wish::PARAM_STATUS => 1,
					Wish::PARAM_DESCRIPTION => 'Make [[search]] results more relevant and faster.',
					Wish::PARAM_TYPE => 2,
					Wish::PARAM_TAGS => [ 0, 8, 9 ],
					Wish::PARAM_AUDIENCE => 'Editors and readers',
					Wish::PARAM_PHAB_TASKS => [ 123, 456 ],
					Wish::PARAM_CREATED => '2023-10-01T12:00:00Z',
					Wish::PARAM_BASE_LANG => 'en',
				]
			],
			[
				[
					Wish::PARAM_TITLE => 'Fix all the bugs ever created',
					Wish::PARAM_STATUS => 'archived',
					Wish::PARAM_DESCRIPTION => 'Fix everything.',
					Wish::PARAM_TYPE => 'bug',
					Wish::PARAM_TAGS => '',
					Wish::PARAM_AUDIENCE => 'Human beings',
					Wish::PARAM_PHAB_TASKS => '',
					Wish::PARAM_CREATED => '2023-10-01T12:00:00Z',
					Wish::PARAM_BASE_LANG => 'en',
				],
				[
					Wish::PARAM_TITLE => 'Fix all the bugs ever created',
					Wish::PARAM_STATUS => 6,
					Wish::PARAM_DESCRIPTION => 'Fix everything.',
					Wish::PARAM_TYPE => 1,
					Wish::PARAM_TAGS => [],
					Wish::PARAM_AUDIENCE => 'Editors and readers',
					Wish::PARAM_PHAB_TASKS => [],
					Wish::PARAM_CREATED => '2023-10-01T12:00:00Z',
					Wish::PARAM_BASE_LANG => 'en',
				]
			]
		];
	}

	public function testGetTagsFromCsv(): void {
		$this->assertSame( [], Wish::getTagsFromCsv( '', $this->config ) );
		$this->assertSame(
			[ 0, 2, 11 ],
			Wish::getTagsFromCsv( 'admins,  categories ,, bogus,patrolling', $this->config )
		);
	}

	public function testGetPhabTasksFromCsv(): void {
		$this->assertSame( [], Wish::getPhabTasksFromCsv( '' ) );
		$this->assertSame(
			[ 123, 456, 789 ],
			Wish::getPhabTasksFromCsv( '  T123,456, ,T789,,' )
		);
	}

	public function testGetTranslationSubpage(): void {
		$wishEn = $this->getTestWish( [] );
		$wishFr = $this->getTestWish( [ Wish::PARAM_BASE_LANG => 'en' ], 'fr' );
		$this->assertSame(
			'Community_Wishlist/Wishes/W123',
			$wishEn->getPage()->getDBkey()
		);
		$this->assertSame(
			'Community_Wishlist/Wishes/W123/en',
			$wishEn->getTranslationSubpage()->getDBkey()
		);
		$this->assertSame(
			'Community_Wishlist/Wishes/W123',
			$wishFr->getPage()->getDBkey()
		);
		$this->assertSame(
			'Community_Wishlist/Wishes/W123/fr',
			$wishFr->getTranslationSubpage()->getDBkey()
		);
	}

	private function getTestWish( array $wishData, string $lang = 'en' ): Wish {
		return new Wish(
			$this->makeMockTitle( 'Community Wishlist/Wishes/W123' ),
			$lang,
			$this->mockRegisteredUltimateAuthority()->getUser(),
			$wishData
		);
	}
}
