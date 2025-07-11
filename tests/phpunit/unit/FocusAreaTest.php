<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MockTitleTrait;

/**
 * @group CommunityRequests
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea
 */
class FocusAreaTest extends AbstractWishlistEntityTest {

	use MockTitleTrait;
	use MockAuthorityTrait;

	/**
	 * @covers ::toWikitext
	 * @dataProvider provideToWikitext
	 */
	public function testToWikitext( array $focusAreaData, string $expectedWikitext ): void {
		$wish = $this->getTestFocusArea( $focusAreaData );

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
					'shortDescription' => '[[Search]] improvements',
					'owners' => '[[Community Tech]]',
					'volunteers' => '[[User:TheDJ|TheDJ]], [[User:Novem Linguae|Novem Linguae]]',
					'created' => '2023-10-01T12:00:00Z',
					'baseLang' => 'en',
				],
				<<<"END"
{{#CommunityRequests: focus-area
| status = submitted
| title = Improve search functionality
| description = Make [[search]] results more relevant and faster.
| short_description = [[Search]] improvements
| owners = [[Community Tech]]
| volunteers = [[User:TheDJ|TheDJ]], [[User:Novem Linguae|Novem Linguae]]
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
	public function testToArray( array $focusAreaData, array $expected ): void {
		$wish = $this->getTestFocusArea( $focusAreaData );
		$this->assertSame( $expected, $wish->toArray( $this->config ) );
	}

	/**
	 * @return array[]
	 */
	public static function provideToArray(): array {
		return [
			[
				[
					'status' => 1,
					'title' => 'Improve search functionality',
					'created' => '2023-10-01T12:00:00Z',
					'baseLang' => 'en',
					'voteCount' => 42,
				],
				[
					'status' => 'submitted',
					'title' => 'Improve search functionality',
					'description' => null,
					'shortDescription' => '',
					'owners' => '',
					'volunteers' => '',
					'created' => '2023-10-01T12:00:00Z',
					'baseLang' => 'en',
					'voteCount' => 42,
				],
			]
		];
	}

	/**
	 * @covers ::newFromWikitextParams
	 * @dataProvider provideNewFromWikitextParams
	 */
	public function testNewFromWikitextParams( $wikitextParams, $expected ): void {
		$focusArea = FocusArea::newFromWikitextParams(
			$this->makeMockTitle( 'Community Wishlist/Focus areas/FA123' ),
			'en',
			$wikitextParams,
			$this->config
		);
		$this->assertSame( $expected[ 'title' ], $focusArea->getTitle() );
		$this->assertSame( $expected[ 'status' ], $focusArea->getStatus() );
		$this->assertSame( $expected[ 'description' ], $focusArea->getDescription() );
		$this->assertSame( $expected[ 'shortDescription' ], $focusArea->getShortDescription() );
		$this->assertSame( $expected[ 'owners' ], $focusArea->getOwners() );
		$this->assertSame( $expected[ 'volunteers' ], $focusArea->getVolunteers() );
		$this->assertSame( $expected[ 'created' ], $focusArea->getCreated() );
	}

	public static function provideNewFromWikitextParams(): array {
		return [
			[
				[
					'title' => 'Improve search functionality',
					'status' => 'submitted',
					'description' => 'Make [[search]] results more relevant and faster.',
					'shortdescription' => '[[Search]] improvements',
					'owners' => '[[Community Tech]]',
					'volunteers' => '[[User:TheDJ|TheDJ]], [[User:Novem Linguae|Novem Linguae]]',
					'created' => '2023-10-01T12:00:00Z',
				],
				[
					'title' => 'Improve search functionality',
					'status' => 1,
					'description' => 'Make [[search]] results more relevant and faster.',
					'shortDescription' => '[[Search]] improvements',
					'owners' => '[[Community Tech]]',
					'volunteers' => '[[User:TheDJ|TheDJ]], [[User:Novem Linguae|Novem Linguae]]',
					'created' => '2023-10-01T12:00:00Z',
				]
			],
			[
				[
					'title' => 'Fix all the bugs ever created',
					'status' => 'archived',
					'description' => 'Fix everything.',
					'shortDescription' => '',
					'created' => '2023-10-01T12:00:00Z',
				],
				[
					'title' => 'Fix all the bugs ever created',
					'status' => 6,
					'description' => 'Fix everything.',
					'shortDescription' => '',
					'owners' => '',
					'volunteers' => '',
					'created' => '2023-10-01T12:00:00Z',
				]
			]
		];
	}

	private function getTestFocusArea( array $focusAreaData ): FocusArea {
		return new FocusArea(
			$this->makeMockTitle( 'Community Wishlist/Focus areas/FA123' ),
			'en',
			$focusAreaData
		);
	}
}
