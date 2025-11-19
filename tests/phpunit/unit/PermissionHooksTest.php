<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Extension\CommunityRequests\HookHandler\PermissionHooks;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Status\Status;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use MockTitleTrait;
use Psr\Log\NullLogger;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\PermissionHooks
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks
 * @covers \MediaWiki\Extension\CommunityRequests\WishlistEntityTrait
 * @covers \MediaWiki\Extension\CommunityRequests\RendererFactory
 */
class PermissionHooksTest extends MediaWikiUnitTestCase {

	use MockWishlistConfigTrait;
	use MockServiceDependenciesTrait;
	use MockAuthorityTrait;
	use MockTitleTrait;

	/**
	 * @dataProvider provideManuallyEditing
	 */
	public function testManuallyEditing(
		array $opts = [],
		bool $expectedReturn = true,
		array $expectedResult = []
	): void {
		$opts = array_merge(
			[
				'title' => $this->makeMockTitle( 'Community Wishlist/W123' ),
				'action' => 'edit',
				'canManuallyEdit' => true,
				'allowManualEditing' => false,
			],
			$opts
		);
		$user = $this->createNoOpMock( User::class, [ '__toString' ] );
		$status = $this->createNoOpMock( Status::class, [ 'getMessages' ] );
		$status->expects( $this->atMost( 1 ) )
			->method( 'getMessages' )
			->willReturn( [ 'badaccess-groups' ] );
		$permissionManager = $this->createNoOpMock(
			PermissionManager::class,
			[ 'userHasRight', 'newFatalPermissionDeniedStatus' ]
		);
		$permissionManager->expects( $this->atMost( 1 ) )
			->method( 'userHasRight' )
			->with( $user, 'manually-edit-wishlist' )
			->willReturn( $opts['canManuallyEdit'] );
		$permissionManager->expects( $this->atMost( 1 ) )
			->method( 'newFatalPermissionDeniedStatus' )
			->with( 'manually-edit-wishlist' )
			->willReturn( $status );
		PermissionHooks::$allowManualEditing = $opts['allowManualEditing'];
		$handler = $this->getHandler( [], $permissionManager );

		$result = [];
		$ret = $handler->onGetUserPermissionsErrorsExpensive(
			$opts['title'],
			$user,
			$opts['action'],
			$result
		);
		$this->assertSame( $expectedReturn, $ret );
		if ( !$expectedReturn ) {
			$this->assertSame( $expectedResult[0][0], $result[0][0] );
			$this->assertSame( $expectedResult[0][1]->getDBKey(), $result[0][1]->getDBKey() );
			$this->assertSame( $expectedResult[1], $result[1] );
		}
	}

	public function provideManuallyEditing(): array {
		return [
			[
				[ 'canManuallyEdit' => true ],
				true,
			],
			[
				[ 'canManuallyEdit' => false ],
				false,
				[
					[ 'communityrequests-cant-manually-edit', $this->makeMockTitle( 'Special:WishlistIntake' ) ],
					'badaccess-groups'
				]
			],
			[
				[
					'canManuallyEdit' => false,
					'allowManualEditing' => true,
				],
				true,
			],
			[
				[
					'title' => $this->makeMockTitle( 'Community Wishlist/FA123' ),
					'canManuallyEdit' => false,
				],
				false,
				[
					[ 'communityrequests-cant-manually-edit', $this->makeMockTitle( 'Special:EditFocusArea' ) ],
					'badaccess-groups'
				]
			],
			[
				[ 'action' => 'view' ],
				true,
			]
		];
	}

	public function testTitleIsMovable(): void {
		$handler = $this->getHandler();

		$title = $this->makeMockTitle( 'Community Wishlist/W123' );
		$result = true;
		$handler->onTitleIsMovable( $title, $result );
		$this->assertFalse( $result );

		$title2 = $this->makeMockTitle( 'Some Other Page' );
		$result2 = true;
		$handler->onTitleIsMovable( $title2, $result2 );
		$this->assertTrue( $result2 );
	}

	private function getHandler(
		array $serviceOptions = [],
		?PermissionManager $permissionManager = null,
	): PermissionHooks {
		return new PermissionHooks(
			$this->getConfig( $serviceOptions ),
			$permissionManager ?: $this->createNoOpMock( PermissionManager::class ),
			$this->getSpecialPageFactory(),
			new NullLogger(),
		);
	}
}
