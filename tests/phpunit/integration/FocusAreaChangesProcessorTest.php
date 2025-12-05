<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaChangesProcessor;
use MediaWiki\Extension\DiscussionTools\SubscriptionItem;
use MediaWiki\Extension\DiscussionTools\SubscriptionStore;
use MediaWiki\Extension\Notifications\Model\Event;
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
	 * @dataProvider provideNotifySubscribers
	 */
	public function testNotifySubscribers( array $expected, array $focusAreaParams, ?array $oldFocusAreaParams ): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Echo' );
		$this->markTestSkippedIfExtensionNotLoaded( 'DiscussionTools' );

		$mockSubscriptionStore = $this->createNoOpMock( SubscriptionStore::class, [ 'getSubscriptionItemsForTopic' ] );
		$mockSubscriptionStore->method( 'getSubscriptionItemsForTopic' )
			->willReturnCallback( function ( string $topic ) {
				// For wish subscriptions, return empty.
				if ( str_starts_with( $topic, 'p-topics-0:Community_Wishlist/W' ) ) {
					return [];
				}
				return [
					$this->createConfiguredMock( SubscriptionItem::class, [
						'getUserIdentity' => $this->getTestUser()->getUserIdentity(),
					] ),
				];
			} );
		$this->setService( 'DiscussionTools.SubscriptionStore', $mockSubscriptionStore );

		$this->setTemporaryHook( 'BeforeEchoEventInsert',
			function ( Event $event ) use ( $expected ) {
				$this->assertSame( $expected['type'], $event->getType() );
				$this->assertSame( 'Community Wishlist/FA123', $event->getTitle()->getPrefixedText() );
				$this->assertSame(
					[ $this->getTestUser()->getUser()->getId() ],
					$event->getExtra()[ Event::RECIPIENTS_IDX ]
				);
				$this->assertArrayContains( $expected['extra'], $event->getExtra() );
			}
		);

		$this->getChangesProcessor( $focusAreaParams, $oldFocusAreaParams )
			->notifySubscribers( 123 );
	}

	public static function provideNotifySubscribers(): array {
		return [
			'focus area status change' => [
				[
					'type' => 'communityrequests-focus-area-status-change',
					'extra' => [
						'entityId' => 'FA123',
						'entityTitle' => 'Test Focus Area',
						'old' => 'under-review',
						'new' => 'done',
					],
				],
				[ FocusArea::PARAM_STATUS => 'done' ],
				[ FocusArea::PARAM_STATUS => 'under-review' ],
			],
		];
	}

	/**
	 * @dataProvider provideAddLogEntries
	 */
	public function testAddLogEntries( array $expected, array $focusAreaParams, ?array $oldFocusAreaParams ): void {
		$this->setTemporaryHook( 'ManualLogEntryBeforePublish',
			function ( ManualLogEntry $logEntry ) use ( $expected ) {
				$this->assertEquals( 'communityrequests', $logEntry->getType() );
				$this->assertEquals( $expected['action'], $logEntry->getSubtype() );
				$this->assertEquals( $expected['params'], $logEntry->getParameters() );
			}
		);

		$this->getChangesProcessor( $focusAreaParams, $oldFocusAreaParams )
			->addLogEntries();
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

	private function getChangesProcessor(
		array $focusAreaParams,
		?array $oldFocusAreaParams
	): FocusAreaChangesProcessor {
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
		return $this->getServiceContainer()
			->get( 'CommunityRequests.ChangesProcessorFactory' )
			->newChangesProcessor( $context, $focusArea, $oldFocusArea );
	}
}
