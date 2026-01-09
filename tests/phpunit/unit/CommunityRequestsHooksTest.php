<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CommunityRequests\EntityFactory;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\DeletePageFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use MockTitleTrait;
use Psr\Log\NullLogger;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks
 * @covers \MediaWiki\Extension\CommunityRequests\WishlistEntityTrait
 * @covers \MediaWiki\Extension\CommunityRequests\RendererFactory
 */
class CommunityRequestsHooksTest extends MediaWikiUnitTestCase {

	use MockWishlistConfigTrait;
	use MockTitleTrait;

	/**
	 * @dataProvider provideUpdateEditLinks
	 */
	public function testUpdateEditLinks(
		array $opts = [],
		array $expectedTabs = []
	): void {
		$opts = array_merge(
			[
				'title' => $this->makeMockTitle( 'Community Wishlist/W123' ),
				'isRegistered' => true,
				'canManuallyEdit' => false,
				'canManage' => false,
				'tabs' => [
					'view' => [],
					've-edit' => [],
					'edit' => [],
					'history' => [],
				],
			],
			$opts
		);
		$user = $this->createNoOpMock( User::class, [ 'isRegistered' ] );
		$user->method( 'isRegistered' )->willReturn( $opts['isRegistered'] );
		$skinTemplate = $this->createNoOpMock( SkinTemplate::class,
			[ 'getUser', 'getTitle', 'getRelevantTitle', 'msg' ]
		);
		$skinTemplate->expects( $this->any() )
			->method( 'getUser' )
			->willReturn( $user );
		$skinTemplate->expects( $this->atMost( 2 ) )
			->method( 'getTitle' )
			->willReturn( $opts['title'] );
		$skinTemplate->expects( $this->atLeastOnce() )
			->method( 'getRelevantTitle' )
			->willReturn( $opts['relevantTitle'] ?? $opts['title'] );
		$skinTemplate->expects( $this->atMost( 1 ) )
			->method( 'msg' )
			->willReturnCallback( [ new FakeQqxMessageLocalizer(), 'msg' ] );
		$permissionManager = $this->createNoOpMock( PermissionManager::class, [ 'userHasRight' ] );
		$permissionManager->expects( $this->any() )
			->method( 'userHasRight' )
			->willReturnMap( [
				[ $user, 'manage-wishlist', $opts['canManage'] ],
				[ $user, 'manually-edit-wishlist', $opts['canManuallyEdit'] ],
			] );
		$handler = $this->getHandler( [], $permissionManager );

		$links = [ 'views' => $opts['tabs'] ];
		$handler->onSkinTemplateNavigation__Universal( $skinTemplate, $links );

		$this->assertSame( $expectedTabs, array_keys( $links['views'] ) );
	}

	public function provideUpdateEditLinks(): array {
		return [
			'default' => [
				[],
				[ 'view', 'wishlist-edit', 'history' ],
			],
			'focus area, default perms' => [
				[ 'title' => $this->makeMockTitle( 'Community Wishlist/FA123' ) ],
				[ 'view', 'history' ],
			],
			'focus area, can manage' => [
				[
					'title' => $this->makeMockTitle( 'Community Wishlist/FA123' ),
					'canManage' => true,
				],
				[ 'view', 'wishlist-edit', 'history' ],
			],
			'wish, default perms' => [
				[ 'title' => $this->makeMockTitle( 'Community Wishlist/W123' ) ],
				[ 'view', 'wishlist-edit', 'history' ],
			],
			'wish, can manually edit' => [
				[
					'title' => $this->makeMockTitle( 'Community Wishlist/W123' ),
					'canManuallyEdit' => true,
				],
				[ 'view', 've-edit', 'edit', 'wishlist-edit', 'history' ],
			],
			'not a wish or FA' => [
				[ 'title' => $this->makeMockTitle( 'Not a wish or FA' ) ],
				[ 'view', 've-edit', 'edit', 'history' ],
			],
			'Special:WishlistIntake/W1, default perms' => [
				[
					'title' => $this->makeMockTitle( 'Special:WishlistIntake/W1', [ 'namespace' => NS_SPECIAL ] ),
					'relevantTitle' => $this->makeMockTitle( 'Community Wishlist/W1' ),
				],
				[ 'view', 'wishlist-edit', 'history' ],
			],
			'Special:WishlistIntake/W1, can manually edit' => [
				[
					'title' => $this->makeMockTitle( 'Special:WishlistIntake/W1', [ 'namespace' => NS_SPECIAL ] ),
					'relevantTitle' => $this->makeMockTitle( 'Community Wishlist/W1' ),
					'canManuallyEdit' => true,
				],
				[ 'view', 've-edit', 'edit', 'wishlist-edit', 'history' ],
			],
			'Special:EditFocusArea/FA1, can manage' => [
				[
					'title' => $this->makeMockTitle( 'Special:EditFocusArea/FA1', [ 'namespace' => NS_SPECIAL ] ),
					'relevantTitle' => $this->makeMockTitle( 'Community Wishlist/FA1' ),
					'canManage' => true,
				],
				[ 'view', 'wishlist-edit', 'history' ],
			],
			'only view tab beforehand' => [
				[ 'tabs' => [ 'view' => [] ] ],
				[ 'view', 'wishlist-edit' ],
			],
			'no applicable tabs beforehand' => [
				[ 'tabs' => [ 'foo' => [], 'bar' => [] ] ],
				[ 'foo', 'bar', 'wishlist-edit' ],
			]
		];
	}

	private function getHandler(
		array $serviceOptions = [],
		?PermissionManager $permissionManager = null,
	): CommunityRequestsHooks {
		$mainConfig = new HashConfig( [ MainConfigNames::PageLanguageUseDB => true ] );
		$extensionRegistry = $this->createNoOpMock( ExtensionRegistry::class, [ 'isLoaded' ] );
		$extensionRegistry->method( 'isLoaded' )->willReturn( false );
		return new CommunityRequestsHooks(
			$this->getConfig( $serviceOptions ),
			$this->createNoOpMock( WishStore::class ),
			$this->createNoOpMock( FocusAreaStore::class ),
			$this->createNoOpMock( EntityFactory::class ),
			$this->createNoOpMock( LinkRenderer::class ),
			$permissionManager ?: $this->createNoOpMock( PermissionManager::class ),
			$this->getSpecialPageFactory(),
			new NullLogger(),
			$mainConfig,
			$this->createNoOpMock( WikiPageFactory::class ),
			$this->createNoOpMock( DeletePageFactory::class ),
			$extensionRegistry
		);
	}
}
