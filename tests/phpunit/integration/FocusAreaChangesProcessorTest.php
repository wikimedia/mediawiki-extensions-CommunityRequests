<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaChangesProcessor;
use MediaWiki\Logging\ManualLogEntry;
use MediaWikiIntegrationTestCase;
use MockTitleTrait;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractChangesProcessor
 * @covers \MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaChangesProcessor
 */
class FocusAreaChangesProcessorTest extends MediaWikiIntegrationTestCase {

	use MockTitleTrait;

	/**
	 * @dataProvider provideAddLogEntries
	 */
	public function testAddLogEntries( array $expected, array $focusAreaParams, ?array $oldFocusAreaParams ): void {
		$config = $this->getServiceContainer()->get( 'CommunityRequests.WishlistConfig' );
		$focusArea = FocusArea::newFromWikitextParams(
			$this->makeMockTitle( 'Community Wishlist/FA123' ),
			'en',
			array_merge( [
				FocusArea::PARAM_TITLE => 'Test Focus Area',
				FocusArea::PARAM_STATUS => 'under-review',
			], $focusAreaParams ),
			$config,
		);
		$oldFocusArea = null;
		if ( $oldFocusAreaParams && count( $oldFocusAreaParams ) ) {
			$oldFocusArea = FocusArea::newFromWikitextParams(
				$this->makeMockTitle( 'Community Wishlist/FA123' ),
				'en',
				array_merge( [
					FocusArea::PARAM_TITLE => 'Test Focus Area',
					FocusArea::PARAM_STATUS => 'under-review',
				], $oldFocusAreaParams ),
				$config,
			);
		}

		$context = RequestContext::getMain();
		$context->setUser( $this->getTestUser()->getUser() );

		/** @var FocusAreaChangesProcessor $changesProcessor */
		$changesProcessor = $this->getServiceContainer()
			->get( 'CommunityRequests.ChangesProcessorFactory' )
			->newChangesProcessor( $context, $focusArea, $oldFocusArea );

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
			'FA creation' => [
				[
					'action' => 'focus-area-create',
					'params' => [],
				],
				[],
				null,
			],
			'FA status change' => [
				[
					'action' => 'focus-area-status-change',
					'params' => [
						'4::old' => 'under-review',
						'5::new' => 'done',
					],
				],
				[ FocusArea::PARAM_STATUS => 'done' ],
				[ FocusArea::PARAM_STATUS => 'under-review' ],
			],
		];
	}
}
