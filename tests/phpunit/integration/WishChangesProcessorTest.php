<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishChangesProcessor;
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
 * @covers \MediaWiki\Extension\CommunityRequests\Wish\WishChangesProcessor
 */
class WishChangesProcessorTest extends MediaWikiIntegrationTestCase {

	use MockTitleTrait;

	/**
	 * @dataProvider provideNotifySubscribers
	 */
	public function testNotifySubscribers( array $expected, array $wishParams, ?array $oldWishParams ): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Echo' );
		$this->markTestSkippedIfExtensionNotLoaded( 'DiscussionTools' );

		$mockSubscriptionStore = $this->createNoOpMock( SubscriptionStore::class, [ 'getSubscriptionItemsForTopic' ] );
		$mockSubscriptionStore->method( 'getSubscriptionItemsForTopic' )
			->willReturnCallback( function ( string $topic ) {
				// For focus area subscriptions, return empty.
				if ( str_starts_with( $topic, 'p-topics-0:Community_Wishlist/FA' ) ) {
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
				$this->assertSame( 'Community Wishlist/W123', $event->getTitle()->getPrefixedText() );
				$this->assertSame(
					[ $this->getTestUser()->getUser()->getId() ],
					$event->getExtra()[ Event::RECIPIENTS_IDX ]
				);
				$this->assertArrayContains( $expected['extra'], $event->getExtra() );
			}
		);

		$this->getChangesProcessor( $wishParams, $oldWishParams )
			->notifySubscribers( 123 );
	}

	public static function provideNotifySubscribers(): array {
		return [
			'wish status change' => [
				[
					'type' => 'communityrequests-wish-status-change',
					'extra' => [
						'entityId' => 'W123',
						'entityTitle' => 'Test Wish',
						'old' => 'under-review',
						'new' => 'done',
					],
				],
				[ Wish::PARAM_STATUS => 'done' ],
				[ Wish::PARAM_STATUS => 'under-review' ],
			],
			'focus area change' => [
				[
					'type' => 'communityrequests-wish-focus-area-change',
					'extra' => [
						'entityId' => 'W123',
						'entityTitle' => 'Test Wish',
						'old' => '',
						'new' => 'FA1',
					],
				],
				[ Wish::PARAM_FOCUS_AREA => '' ],
				[ Wish::PARAM_FOCUS_AREA => 'FA1' ],
			],
		];
	}

	/**
	 * @dataProvider provideAddLogEntries
	 */
	public function testAddLogEntries( array $expected, array $wishParams, ?array $oldWishParams ): void {
		$this->setTemporaryHook( 'ManualLogEntryBeforePublish',
			function ( ManualLogEntry $logEntry ) use ( $expected ) {
				$this->assertEquals( 'communityrequests', $logEntry->getType() );
				$this->assertEquals( $expected['action'], $logEntry->getSubtype() );
				$this->assertEquals( $expected['params'], $logEntry->getParameters() );
			}
		);

		$this->getChangesProcessor( $wishParams, $oldWishParams )
			->addLogEntries();
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

	private function getChangesProcessor( array $wishParams, ?array $oldWishParams ): WishChangesProcessor {
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
		return $this->getServiceContainer()
			->get( 'CommunityRequests.ChangesProcessorFactory' )
			->newChangesProcessor( $context, $wish, $oldWish );
	}
}
