<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\CommunityRequestsLogFormatter;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Tests\Logging\LogFormatterTestCase;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\TitleFactory;
use MockTitleTrait;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\CommunityRequestsLogFormatter
 */
class CommunityRequestsLogFormatterTest extends LogFormatterTestCase {

	use MockAuthorityTrait;
	use MockTitleTrait;

	public static function provideLogDatabaseRows(): array {
		return [
			'wish-create' => [
				'row' => [
					'type' => 'communityrequests',
					'action' => 'wish-create',
					'title' => 'Community_Wishlist/W123',
					'namespace' => NS_MAIN,
					'user_text' => 'TestUser',
					'params' => [],
				],
				'extra' => [
					'text' => 'TestUser created the wish "Test Wish (Community Wishlist/W123)"',
					'api' => [],
				]
			],
			'wish-status-change' => [
				'row' => [
					'type' => 'communityrequests',
					'action' => 'wish-status-change',
					'title' => 'Community_Wishlist/W123',
					'namespace' => NS_MAIN,
					'user_text' => 'TestUser',
					'params' => [
						'4::old' => 'under-review',
						'5::new' => 'prioritized'
					],
				],
				'extra' => [
					'text' => 'TestUser changed the status of "Test Wish (Community Wishlist/W123)" ' .
						'from "Under review" to "Prioritized"',
					'api' => [
						'old' => 'under-review',
						'new' => 'prioritized',
					],
				]
			],
			'wish-focusarea-change' => [
				'row' => [
					'type' => 'communityrequests',
					'action' => 'wish-focusarea-change',
					'title' => 'Community_Wishlist/W123',
					'namespace' => NS_MAIN,
					'user_text' => 'TestUser',
					'params' => [ '4::old' => 'FA1', '5::new' => 'FA2' ],
				],
				'extra' => [
					'text' => 'TestUser changed the focus area of "Test Wish (Community Wishlist/W123)" ' .
						'from "Focus Area 1 (Community Wishlist/FA1)" to "Focus Area 2 (Community Wishlist/FA2)"',
					'api' => [
						'old' => 'FA1',
						'new' => 'FA2',
					],
				]
			],
			'wish-focusarea-change (from null)' => [
				'row' => [
					'type' => 'communityrequests',
					'action' => 'wish-focusarea-change',
					'title' => 'Community_Wishlist/W123',
					'namespace' => NS_MAIN,
					'user_text' => 'TestUser',
					'params' => [ '4::old' => null, '5::new' => 'FA1' ],
				],
				'extra' => [
					'text' => 'TestUser changed the focus area of "Test Wish (Community Wishlist/W123)" ' .
						'from "Unassigned" to "Focus Area 1 (Community Wishlist/FA1)"',
					'api' => [
						'old' => null,
						'new' => 'FA1',
					],
				]
			],
			'wish-focusarea-change (to null)' => [
				'row' => [
					'type' => 'communityrequests',
					'action' => 'wish-focusarea-change',
					'title' => 'Community_Wishlist/W123',
					'namespace' => NS_MAIN,
					'user_text' => 'TestUser',
					'params' => [ '4::old' => 'FA1', '5::new' => null ],
				],
				'extra' => [
					'text' => 'TestUser changed the focus area of "Test Wish (Community Wishlist/W123)" ' .
						'from "Focus Area 1 (Community Wishlist/FA1)" to "Unassigned"',
					'api' => [
						'old' => 'FA1',
						'new' => null,
					],
				]
			],
			'focus-area-create' => [
				'row' => [
					'type' => 'communityrequests',
					'action' => 'focus-area-create',
					'title' => 'Community_Wishlist/FA1',
					'namespace' => NS_MAIN,
					'user_text' => 'TestUser',
					'params' => [],
				],
				'extra' => [
					'text' => 'TestUser created the focus area "Focus Area 1 (Community Wishlist/FA1)"',
					'api' => [],
				]
			],
			'focus-area-status-change' => [
				'row' => [
					'type' => 'communityrequests',
					'action' => 'focus-area-status-change',
					'title' => 'Community_Wishlist/FA1',
					'namespace' => NS_MAIN,
					'user_text' => 'TestUser',
					'params' => [
						'4::old' => 'community-opportunity',
						'5::new' => 'declined'
					],
				],
				'extra' => [
					'text' => 'TestUser changed the status of "Focus Area 1 (Community Wishlist/FA1)" ' .
						'from "Community opportunity" to "Declined"',
					'api' => [
						'old' => 'community-opportunity',
						'new' => 'declined',
					],
				]
			],
		];
	}

	/**
	 * @dataProvider provideLogDatabaseRows
	 */
	public function testLogDatabaseRows( array $row, array $extra ): void {
		$wishTitle = $this->makeMockTitle( 'Community_Wishlist/W123' );
		$wish = new Wish( $wishTitle, 'en', $this->mockRegisteredUltimateAuthority()->getUser(), [
			Wish::PARAM_TITLE => 'Test Wish',
		] );
		$wishStore = $this->createMock( WishStore::class );
		$wishStore->method( 'get' )->willReturn( $wish );
		$wishStore->method( 'entityType' )->willReturn( 'wish' );
		$this->setService( 'CommunityRequests.WishStore', $wishStore );

		$focusAreaTitle1 = $this->makeMockTitle( 'Community_Wishlist/FA1' );
		$focusArea1 = new FocusArea( $focusAreaTitle1, 'en', [
			FocusArea::PARAM_TITLE => 'Focus Area 1',
		] );
		$focusAreaTitle2 = $this->makeMockTitle( 'Community_Wishlist/FA2' );
		$focusArea2 = new FocusArea( $focusAreaTitle2, 'en', [
			FocusArea::PARAM_TITLE => 'Focus Area 2',
		] );
		$focusAreaStore = $this->createMock( FocusAreaStore::class );
		$focusAreaStore->method( 'get' )->willReturnCallback(
			static fn ( $title ) => $title->getDBKey() === 'Community_Wishlist/FA1' ? $focusArea1 : $focusArea2
		);
		$focusAreaStore->method( 'entityType' )->willReturn( 'focus-area' );
		$this->setService( 'CommunityRequests.FocusAreaStore', $focusAreaStore );

		$dbKeyMatcher = static fn ( string $dbKey ) => match ( $dbKey ) {
			'Community_Wishlist/W123' => $wishTitle,
			'Community_Wishlist/FA1' => $focusAreaTitle1,
			'Community_Wishlist/FA2' => $focusAreaTitle2,
		};
		$titleFactory = $this->createNoOpMock( TitleFactory::class, [ 'makeTitle', 'newFromPageReference' ] );
		$titleFactory->method( 'makeTitle' )->willReturnCallback(
			static fn ( $ns, $title ) => $dbKeyMatcher( $title )
		);
		$titleFactory->method( 'newFromPageReference' )->willReturnCallback(
			static fn ( $pageRef ) => $dbKeyMatcher( $pageRef->getDBKey() )
		);
		$this->setService( 'TitleFactory', $titleFactory );

		CommunityRequestsLogFormatter::clearEntityCache();
		$this->setGroupPermissions( 'sysop', 'manage-wishlist', true );
		$this->doTestLogFormatter( $row, $extra, [ 'community-wishlist-manager' ] );
	}

	/**
	 * @dataProvider provideGetPreloadTitles
	 */
	public function testGetPreloadTitles( string $action, array $params, array $expectedDBKeys ): void {
		$logEntry = new ManualLogEntry( 'communityrequests', $action );
		$logEntry->setTarget( $this->makeMockTitle( 'Community_Wishlist/W123' ) );
		$logEntry->setPerformer( $this->mockRegisteredUltimateAuthority()->getUser() );
		$logEntry->setParameters( $params );

		$formatter = new CommunityRequestsLogFormatter(
			$logEntry,
			$this->getServiceContainer()->get( 'CommunityRequests.WishlistConfig' ),
			$this->getServiceContainer()->get( 'CommunityRequests.WishStore' ),
			$this->getServiceContainer()->get( 'CommunityRequests.FocusAreaStore' ),
			$this->getServiceContainer()->get( 'TitleFormatter' ),
			$this->getServiceContainer()->get( 'TitleFactory' ),
			$this->getServiceContainer()->get( 'CommunityRequests.Logger' ),
		);

		$preloadTitles = $formatter->getPreloadTitles();
		$preloadDBKeys = array_map(
			static fn ( $title ) => $title->getDBKey(),
			$preloadTitles
		);
		$this->assertSame( $expectedDBKeys, $preloadDBKeys );
	}

	public static function provideGetPreloadTitles(): array {
		return [
			'wish-create' => [
				'action' => 'wish-create',
				'params' => [],
				'expectedDBKeys' => [],
			],
			'wish-status-change' => [
				'action' => 'wish-status-change',
				'params' => [ '4::old' => 'under-review', '5::new' => 'prioritized' ],
				'expectedDBKeys' => [],
			],
			'wish-focusarea-change' => [
				'action' => 'wish-focusarea-change',
				'params' => [ '4::old' => 'FA1', '5::new' => 'FA2' ],
				'expectedDBKeys' => [ 'Community_Wishlist/FA1', 'Community_Wishlist/FA2' ],
			],
			'focus-area-create' => [
				'action' => 'focus-area-create',
				'params' => [],
				'expectedDBKeys' => [],
			],
		];
	}
}
