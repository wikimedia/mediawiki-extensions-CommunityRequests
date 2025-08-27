<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MockTitleTrait;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea
 */
class FocusAreaTest extends AbstractWishlistEntityTest {

	use MockTitleTrait;
	use MockAuthorityTrait;

	/**
	 * @dataProvider provideToWikitext
	 */
	public function testToWikitext( array $focusAreaData, string $expectedWikitext ): void {
		$focusArea = $this->getTestFocusArea( $focusAreaData );

		$this->assertSame(
			$expectedWikitext,
			$focusArea->toWikitext( $this->config )->getText()
		);
	}

	/**
	 * @return array[]
	 */
	public static function provideToWikitext(): array {
		return [
			[
				[
					FocusArea::PARAM_TITLE => 'Improve search functionality',
					FocusArea::PARAM_STATUS => 1,
					FocusArea::PARAM_DESCRIPTION => 'Make [[search]] results more relevant and faster.',
					FocusArea::PARAM_SHORT_DESCRIPTION => '[[Search]] improvements',
					FocusArea::PARAM_OWNERS => '[[Community Tech]]',
					FocusArea::PARAM_VOLUNTEERS => '[[User:TheDJ|TheDJ]], [[User:Novem Linguae|Novem Linguae]]',
					FocusArea::PARAM_CREATED => '2023-10-01T12:00:00Z',
					FocusArea::PARAM_BASE_LANG => 'en',
				],
				<<<"END"
{{#CommunityRequests: focus-area
| status = submitted
| title = Improve search functionality
| description = Make [[search]] results more relevant and faster.
| shortdescription = [[Search]] improvements
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
	 * @dataProvider provideToArray
	 */
	public function testToArray( array $focusAreaData, array $expected ): void {
		$focusArea = $this->getTestFocusArea( $focusAreaData );
		$this->assertSame( $expected, $focusArea->toArray( $this->config ) );
	}

	/**
	 * @return array[]
	 */
	public static function provideToArray(): array {
		return [
			[
				[
					FocusArea::PARAM_STATUS => 1,
					FocusArea::PARAM_TITLE => 'Improve search functionality',
					FocusArea::PARAM_CREATED => '2023-10-01T12:00:00Z',
					FocusArea::PARAM_BASE_LANG => 'en',
					FocusArea::PARAM_VOTE_COUNT => 42,
				],
				[
					FocusArea::PARAM_STATUS => 'submitted',
					FocusArea::PARAM_TITLE => 'Improve search functionality',
					FocusArea::PARAM_DESCRIPTION => null,
					FocusArea::PARAM_SHORT_DESCRIPTION => '',
					FocusArea::PARAM_OWNERS => '',
					FocusArea::PARAM_VOLUNTEERS => '',
					FocusArea::PARAM_CREATED => '2023-10-01T12:00:00Z',
					FocusArea::PARAM_BASE_LANG => 'en',
					FocusArea::PARAM_VOTE_COUNT => 42,
				],
			]
		];
	}

	/**
	 * @dataProvider provideNewFromWikitextParams
	 */
	public function testNewFromWikitextParams( $wikitextParams, $expected ): void {
		$focusArea = FocusArea::newFromWikitextParams(
			$this->makeMockTitle( 'Community Wishlist/Focus areas/FA123' ),
			'en',
			$wikitextParams,
			$this->config
		);
		$this->assertSame( $expected[FocusArea::PARAM_TITLE], $focusArea->getTitle() );
		$this->assertSame( $expected[FocusArea::PARAM_STATUS], $focusArea->getStatus() );
		$this->assertSame( $expected[FocusArea::PARAM_DESCRIPTION], $focusArea->getDescription() );
		$this->assertSame( $expected[FocusArea::PARAM_SHORT_DESCRIPTION], $focusArea->getShortDescription() );
		$this->assertSame( $expected[FocusArea::PARAM_OWNERS], $focusArea->getOwners() );
		$this->assertSame( $expected[FocusArea::PARAM_VOLUNTEERS], $focusArea->getVolunteers() );
		$this->assertSame( $expected[FocusArea::PARAM_CREATED], $focusArea->getCreated() );
	}

	public static function provideNewFromWikitextParams(): array {
		return [
			[
				[
					FocusArea::PARAM_TITLE => 'Improve search functionality',
					FocusArea::PARAM_STATUS => 'submitted',
					FocusArea::PARAM_DESCRIPTION => 'Make [[search]] results more relevant and faster.',
					FocusArea::PARAM_SHORT_DESCRIPTION => '[[Search]] improvements',
					FocusArea::PARAM_OWNERS => '[[Community Tech]]',
					FocusArea::PARAM_VOLUNTEERS => '[[User:TheDJ|TheDJ]], [[User:Novem Linguae|Novem Linguae]]',
					FocusArea::PARAM_CREATED => '2023-10-01T12:00:00Z',
				],
				[
					FocusArea::PARAM_TITLE => 'Improve search functionality',
					FocusArea::PARAM_STATUS => 1,
					FocusArea::PARAM_DESCRIPTION => 'Make [[search]] results more relevant and faster.',
					FocusArea::PARAM_SHORT_DESCRIPTION => '[[Search]] improvements',
					FocusArea::PARAM_OWNERS => '[[Community Tech]]',
					FocusArea::PARAM_VOLUNTEERS => '[[User:TheDJ|TheDJ]], [[User:Novem Linguae|Novem Linguae]]',
					FocusArea::PARAM_CREATED => '2023-10-01T12:00:00Z',
				]
			],
			[
				[
					FocusArea::PARAM_TITLE => 'Fix all the bugs ever created',
					FocusArea::PARAM_STATUS => 'archived',
					FocusArea::PARAM_DESCRIPTION => 'Fix everything.',
					FocusArea::PARAM_SHORT_DESCRIPTION => '',
					FocusArea::PARAM_CREATED => '2023-10-01T12:00:00Z',
				],
				[
					FocusArea::PARAM_TITLE => 'Fix all the bugs ever created',
					FocusArea::PARAM_STATUS => 6,
					FocusArea::PARAM_DESCRIPTION => 'Fix everything.',
					FocusArea::PARAM_SHORT_DESCRIPTION => '',
					FocusArea::PARAM_OWNERS => '',
					FocusArea::PARAM_VOLUNTEERS => '',
					FocusArea::PARAM_CREATED => '2023-10-01T12:00:00Z',
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
