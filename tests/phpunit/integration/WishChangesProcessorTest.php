<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishChangesProcessor;
use MediaWiki\Logging\ManualLogEntry;
use MediaWikiIntegrationTestCase;
use MockTitleTrait;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractChangesProcessor
 * @covers \MediaWiki\Extension\CommunityRequests\Wish\WishChangesProcessor
 */
class WishChangesProcessorTest extends MediaWikiIntegrationTestCase {

	use MockTitleTrait;

	/**
	 * @dataProvider provideAddLogEntries
	 */
	public function testAddLogEntries( array $expected, array $wishParams, ?array $oldWishParams ): void {
		$config = $this->getServiceContainer()->get( 'CommunityRequests.WishlistConfig' );
		$wish = Wish::newFromWikitextParams(
			$this->makeMockTitle( 'Community Wishlist/W123' ),
			'en',
			array_merge( [
				Wish::PARAM_TITLE => 'Test Wish',
				Wish::PARAM_STATUS => 'under-review',
				Wish::PARAM_TYPE => 'change',
			], $wishParams ),
			$config,
			$this->getTestUser()->getUserIdentity(),
		);
		$oldWish = null;
		if ( $oldWishParams && count( $oldWishParams ) ) {
			$oldWish = Wish::newFromWikitextParams(
				$this->makeMockTitle( 'Community Wishlist/W123' ),
				'en',
				array_merge( [
					Wish::PARAM_TITLE => 'Test Wish',
					Wish::PARAM_STATUS => 'under-review',
					Wish::PARAM_TYPE => 'change',
				], $oldWishParams ),
				$config,
				$this->getTestUser()->getUserIdentity(),
			);
		}

		$faTitle1 = $this->makeMockTitle( 'Community Wishlist/FA1' );
		$focusArea1 = FocusArea::newFromWikitextParams(
			$faTitle1,
			'en',
			[ FocusArea::PARAM_TITLE => 'Focus Area 1', FocusArea::PARAM_STATUS => 'prioritized' ],
			$config,
		);
		$faTitle2 = $this->makeMockTitle( 'Community Wishlist/FA2' );
		$focusArea2 = FocusArea::newFromWikitextParams(
			$faTitle2,
			'en',
			[ FocusArea::PARAM_TITLE => 'Focus Area 2', FocusArea::PARAM_STATUS => 'prioritized' ],
			$config,
		);
		$mockFocusAreaStore = $this->createNoOpMock( FocusAreaStore::class, [ 'get' ] );
		$mockFocusAreaStore->method( 'get' )
			->willReturnMap( [
				[ $faTitle1, $focusArea1 ],
				[ $faTitle2, $focusArea2 ],
			] );
		$this->setService( 'CommunityRequests.FocusAreaStore', $mockFocusAreaStore );

		$context = RequestContext::getMain();
		$context->setUser( $this->getTestUser()->getUser() );

		/** @var WishChangesProcessor $changesProcessor */
		$changesProcessor = $this->getServiceContainer()
			->get( 'CommunityRequests.ChangesProcessorFactory' )
			->newChangesProcessor( $context, $wish, $oldWish );

		$this->setTemporaryHook( 'ManualLogEntryBeforePublish',
			function ( ManualLogEntry $logEntry ) use ( $expected ) {
				$this->assertEquals( 'communityrequests', $logEntry->getType() );
				$this->assertEquals( $expected['action'], $logEntry->getSubtype() );
				$this->assertEquals( $expected['params'], $logEntry->getParameters() );
			}
		);

		$changesProcessor->addLogEntries();
	}

	public static function provideAddLogEntries(): array {
		return [
			'wish creation' => [
				[
					'action' => 'wish-create',
					'params' => [],
				],
				[],
				null,
			],
			'wish status change' => [
				[
					'action' => 'wish-status-change',
					'params' => [
						'4::old' => 'under-review',
						'5::new' => 'done',
					],
				],
				[ Wish::PARAM_STATUS => 'done' ],
				[ Wish::PARAM_STATUS => 'under-review' ],
			],
			'focus area change' => [
				[
					'action' => 'wish-focusarea-change',
					'params' => [
						'4::old' => 'FA1',
						'5::new' => '',
					],
				],
				[ Wish::PARAM_FOCUS_AREA => '' ],
				[ Wish::PARAM_FOCUS_AREA => 'FA1' ],
			],
		];
	}
}
