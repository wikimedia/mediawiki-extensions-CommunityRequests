<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use Language;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityRequests\EntityFactory;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\Extension\CommunityRequests\Vote\VoteStore;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Language\LanguageNameUtils;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\Session;
use MediaWiki\Skin\Skin;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Status\Status;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleParser;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use MockTitleTrait;
use Psr\Log\NullLogger;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks
 * @covers \MediaWiki\Extension\CommunityRequests\RendererFactory
 */
class CommunityRequestsHooksTest extends MediaWikiUnitTestCase {

	use MockServiceDependenciesTrait;
	use MockAuthorityTrait;
	use MockTitleTrait;
	use MockServiceDependenciesTrait;

	/**
	 * @dataProvider provideOnBeforePageDisplay
	 */
	public function testOnBeforePageDisplay( array $opts = [], array $expectedModules = [] ): void {
		$opts = array_merge(
			[
				'enabled' => true,
				'wishVotingEnabled' => true,
				'focusAreaVotingEnabled' => true,
				'title' => $this->makeMockTitle( 'Community Wishlist/W123' ),
				'postEditVal' => null,
				'prefersMachineTranslation' => false,
			],
			$opts
		);

		$out = $this->createNoOpMock( OutputPage::class, [
			'addJsConfigVars',
			'addModules',
			'addModuleStyles',
			'getRequest',
			'getTitle',
			'getUser',
		] );
		$out->expects( $this->atLeast( count( $expectedModules ) ) )
			->method( 'addModules' )
			->willReturnMap(
				array_map(
					static fn ( $m ) => [ $m ],
					$expectedModules
				)
			);
		$out->method( 'getTitle' )->willReturn( $opts['title'] );
		$out->expects( $this->atMost( 2 ) )
			->method( 'getUser' )
			->willReturn( $this->createMock( User::class ) );
		if ( in_array( 'ext.communityrequests.voting', $expectedModules ) ) {
			$out->expects( $this->atMost( 1 ) )
				->method( 'addJsConfigVars' )
				->with( 'crPostEdit', $opts['postEditVal'] );
		}
		$session = $this->createNoOpMock( Session::class, [ 'get', 'remove' ] );
		if ( $opts['postEditVal'] !== null ) {
			$session->expects( $this->exactly( 2 ) )
				->method( 'get' )
				->with( CommunityRequestsHooks::SESSION_KEY )
				->willReturn( $opts['postEditVal'] );
			$session->expects( $this->once() )
				->method( 'remove' );
		} else {
			$session->expects( $this->atMost( 1 ) )->method( 'get' );
			$session->expects( $this->never() )->method( 'remove' );
		}
		$webRequest = $this->createNoOpMock( WebRequest::class, [ 'getSession' ] );
		$webRequest->expects( $this->atMost( 3 ) )
			->method( 'getSession' )
			->willReturn( $session );
		$out->expects( $this->atMost( 3 ) )
			->method( 'getRequest' )
			->willReturn( $webRequest );

		$userOptionsManager = $this->createNoOpMock( UserOptionsManager::class, [ 'getBoolOption' ] );
		$userOptionsManager->expects( $this->atMost( 1 ) )
			->method( 'getBoolOption' )
			->willReturn( $opts['prefersMachineTranslation'] );
		$handler = $this->getHandler( [
			WishlistConfig::ENABLED => $opts['enabled'],
			WishlistConfig::WISH_VOTING_ENABLED => $opts['wishVotingEnabled'],
			WishlistConfig::FOCUS_AREA_VOTING_ENABLED => $opts['focusAreaVotingEnabled'],
			WishlistConfig::WISH_PAGE_PREFIX => 'Community Wishlist/W',
			WishlistConfig::FOCUS_AREA_PAGE_PREFIX => 'Community Wishlist/FA',
			WishlistConfig::WISH_INDEX_PAGE => 'Community Wishlist',
			WishlistConfig::FOCUS_AREA_INDEX_PAGE => 'Community Wishlist/Focus areas',
		], null, $userOptionsManager );
		$handler->onBeforePageDisplay( $out, $this->createNoOpMock( Skin::class ) );
	}

	public function provideOnBeforePageDisplay(): array {
		return [
			'disabled' => [
				[ 'enabled' => false ],
				[]
			],
			'non-wish page' => [
				[ 'title' => $this->makeMockTitle( 'Some other page' ) ],
				[],
			],
			'post-edit new wish' => [
				[
					'title' => $this->makeMockTitle( 'Community Wishlist/W123' ),
					'postEditVal' => CommunityRequestsHooks::SESSION_VALUE_ENTITY_CREATED,
				],
				[ 'ext.communityrequests.voting' ],
			],
			'post-edit, vote added' => [
				[
					'title' => $this->makeMockTitle( 'Community Wishlist/W123' ),
					'postEditVal' => CommunityRequestsHooks::SESSION_VALUE_VOTE_ADDED,
				],
				[ 'ext.communityrequests.voting' ],
			],
			'view focus area' => [
				[ 'title' => $this->makeMockTitle( 'Community Wishlist/FA123' ) ],
				[ 'ext.communityrequests.voting' ],
			],
			'view wish, voting disabled' => [
				[
					'title' => $this->makeMockTitle( 'Community Wishlist/W123' ),
					'wishVotingEnabled' => false,
				],
				[],
			],
			'view wish, prefers machine translation' => [
				[
					'title' => $this->makeMockTitle( 'Community Wishlist/W123' ),
					'prefersMachineTranslation' => true,
				],
				[ 'ext.communityrequests.voting', 'ext.communityrequests.mint' ],
			],
		];
	}

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
		$skinTemplate = $this->createNoOpMock( SkinTemplate::class, [ 'getUser', 'getTitle', 'msg' ] );
		$skinTemplate->expects( $this->any() )
			->method( 'getUser' )
			->willReturn( $user );
		$skinTemplate->expects( $this->atLeastOnce() )
			->method( 'getTitle' )
			->willReturn( $opts['title'] );
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
		$handler = $this->getHandler(
			[
				WishlistConfig::WISH_PAGE_PREFIX => 'Community Wishlist/W',
				WishlistConfig::VOTES_PAGE_SUFFIX => '/Votes',
				WishlistConfig::FOCUS_AREA_PAGE_PREFIX => 'Community Wishlist/FA',
			],
			$permissionManager,
			null,
		);

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
				[ 'title' => $this->makeMockTitle( 'Special:WishlistIntake/W1', [ 'namespace' => NS_SPECIAL ] ) ],
				[ 'view', 'wishlist-edit', 'history' ],
			],
			'Special:WishlistIntake/W1, can manually edit' => [
				[
					'title' => $this->makeMockTitle( 'Special:WishlistIntake/W1', [ 'namespace' => NS_SPECIAL ] ),
					'canManuallyEdit' => true,
				],
				[ 'view', 've-edit', 'edit', 'wishlist-edit', 'history' ],
			],
			'Special:EditFocusArea/FA1, can manage' => [
				[
					'title' => $this->makeMockTitle( 'Special:EditFocusArea/FA1', [ 'namespace' => NS_SPECIAL ] ),
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
		CommunityRequestsHooks::$allowManualEditing = $opts['allowManualEditing'];
		$handler = $this->getHandler(
			[
				WishlistConfig::WISH_PAGE_PREFIX => 'Community Wishlist/W',
				WishlistConfig::FOCUS_AREA_PAGE_PREFIX => 'Community Wishlist/FA',
			],
			$permissionManager
		);

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

	public function testBeforeDisplayNoArticleText(): void {
		$handler = $this->getHandler(
			[
				WishlistConfig::WISH_PAGE_PREFIX => 'Community Wishlist/W',
				WishlistConfig::FOCUS_AREA_PAGE_PREFIX => 'Community Wishlist/FA',
			]
		);
		$testTitle = $this->makeMockTitle( 'Community Wishlist/W9999' );
		$language = $this->createNoOpMock( Language::class, [ 'getDir', 'getHtmlCode' ] );
		$language->expects( $this->once() )
			->method( 'getDir' )
			->willReturn( 'ltr' );
		$language->expects( $this->once() )
			->method( 'getHtmlCode' )
			->willReturn( 'en' );
		$outputPage = $this->createNoOpMock( OutputPage::class, [ 'addWikiTextAsInterface' ] );
		$outputPage->expects( $this->once() )
			->method( 'addWikiTextAsInterface' );
		$context = $this->createNoOpMock( RequestContext::class, [ 'getLanguage', 'getOutput', 'msg' ] );
		$context->expects( $this->exactly( 2 ) )
			->method( 'getLanguage' )
			->willReturn( $language );
		$context->expects( $this->once() )
			->method( 'getOutput' )
			->willReturn( $outputPage );
		$context->expects( $this->once() )
			->method( 'msg' )
			->willReturnCallback( [ new FakeQqxMessageLocalizer(), 'msg' ] );
		$article = $this->createNoOpMock( Article::class, [ 'getTitle', 'getOldID', 'getContext' ] );
		$article->expects( $this->exactly( 2 ) )
			->method( 'getTitle' )
			->willReturn( $testTitle );
		$article->expects( $this->once() )
			->method( 'getOldID' )
			->willReturn( 0 );
		$article->expects( $this->once() )
			->method( 'getContext' )
			->willReturn( $context );

		$handler->onBeforeDisplayNoArticleText( $article );
	}

	private function getHandler(
		array $serviceOptions = [],
		?PermissionManager $permissionManager = null,
		?UserOptionsManager $userOptionsManager = null,
		?ExtensionRegistry $extensionRegistry = null,
	): CommunityRequestsHooks {
		$serviceOptions = new ServiceOptions( WishlistConfig::CONSTRUCTOR_OPTIONS, [
			WishlistConfig::ENABLED => true,
			WishlistConfig::HOMEPAGE => '',
			WishlistConfig::WISH_CATEGORY => '',
			WishlistConfig::WISH_PAGE_PREFIX => '',
			WishlistConfig::WISH_INDEX_PAGE => '',
			WishlistConfig::WISH_TYPES => [],
			WishlistConfig::FOCUS_AREA_CATEGORY => '',
			WishlistConfig::FOCUS_AREA_PAGE_PREFIX => '',
			WishlistConfig::FOCUS_AREA_INDEX_PAGE => '',
			WishlistConfig::TAGS => [],
			WishlistConfig::STATUSES => [],
			WishlistConfig::VOTES_PAGE_SUFFIX => '',
			WishlistConfig::WISH_VOTING_ENABLED => true,
			WishlistConfig::FOCUS_AREA_VOTING_ENABLED => true,
			MainConfigNames::LanguageCode => 'en',
			...$serviceOptions
		] );
		$config = new WishlistConfig(
			$serviceOptions,
			$this->newServiceInstance( TitleParser::class, [ 'localInterwikis' => [] ] ),
			$this->newServiceInstance( TitleFormatter::class, [] ),
			$this->newServiceInstance( LanguageNameUtils::class, [] ),
		);
		$mainConfig = new HashConfig( [ MainConfigNames::PageLanguageUseDB => true ] );
		$extensionRegistry = $extensionRegistry ?: $this->createNoOpMock( ExtensionRegistry::class, [ 'isLoaded' ] );
		$extensionRegistry->method( 'isLoaded' )->willReturn( false );
		return new CommunityRequestsHooks(
			$config,
			$this->createNoOpMock( WishStore::class ),
			$this->createNoOpMock( FocusAreaStore::class ),
			$this->createNoOpMock( VoteStore::class ),
			$this->createNoOpMock( EntityFactory::class ),
			$this->createNoOpMock( LinkRenderer::class ),
			$permissionManager ?: $this->createNoOpMock( PermissionManager::class ),
			$this->getSpecialPageFactory(),
			$userOptionsManager ?: $this->createNoOpMock( UserOptionsManager::class ),
			new NullLogger(),
			$mainConfig,
			$this->createNoOpMock( WikiPageFactory::class ),
			$extensionRegistry
		);
	}

	private function getSpecialPageFactory(): SpecialPageFactory {
		$specialWishlistIntake = $this->createNoOpMock( SpecialPage::class, [ 'getPageTitle' ] );
		$specialWishlistIntake->expects( $this->any() )
			->method( 'getPageTitle' )
			->willReturn( $this->makeMockTitle( 'WishlistIntake', [ 'namespace' => NS_SPECIAL ] ) );
		$specialEditFocusArea = $this->createNoOpMock( SpecialPage::class, [ 'getPageTitle' ] );
		$specialEditFocusArea->expects( $this->any() )
			->method( 'getPageTitle' )
			->willReturn( $this->makeMockTitle( 'EditFocusArea', [ 'namespace' => NS_SPECIAL ] ) );
		$specialPageFactory = $this->createNoOpMock( SpecialPageFactory::class, [ 'getPage' ] );
		$specialPageFactory->expects( $this->atMost( 1 ) )
			->method( 'getPage' )
			->willReturnMap( [
				[ 'WishlistIntake', $specialWishlistIntake ],
				[ 'EditFocusArea', $specialEditFocusArea ],
			] );
		return $specialPageFactory;
	}
}
