<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\Extension\CommunityRequests\HookHandler\PageDisplayHooks;
use MediaWiki\Extension\CommunityRequests\Vote\VoteStore;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\Session;
use MediaWiki\Skin\Skin;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use MockTitleTrait;
use Psr\Log\NullLogger;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\PageDisplayHooks
 */
class PageDisplayHooksTest extends MediaWikiUnitTestCase {

	use MockWishlistConfigTrait;
	use MockServiceDependenciesTrait;
	use MockAuthorityTrait;
	use MockTitleTrait;

	/**
	 * @dataProvider provideOnBeforePageDisplay
	 */
	public function testOnBeforePageDisplay(
		array $opts = [],
		array $expectedModules = [],
		bool $expectStylesModule = true
	): void {
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
			'addBodyClasses',
			'addJsConfigVars',
			'addModules',
			'addModuleStyles',
			'getRequest',
			'getTitle',
			'getUser',
		] );
		$out->expects( $this->atMost( 1 ) )
			->method( 'addBodyClasses' );
		$out->expects( $this->atLeast( count( $expectedModules ) ) )
			->method( 'addModules' )
			->willReturnMap(
				array_map(
					static fn ( $m ) => [ $m ],
					$expectedModules
				)
			);
		$out->expects( $expectStylesModule ? $this->once() : $this->never() )
			->method( 'addModuleStyles' )
			->with( 'ext.communityrequests.styles' );
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
		], null, $userOptionsManager );
		$handler->onBeforePageDisplay( $out, $this->createNoOpMock( Skin::class ) );
	}

	public function provideOnBeforePageDisplay(): array {
		return [
			'disabled' => [
				[ 'enabled' => false ],
				[],
				false,
			],
			'non-wish page' => [
				[ 'title' => $this->makeMockTitle( 'Some other page' ) ],
				[],
				false,
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
			'view wish talk page' => [
				[
					'title' => $this->makeMockTitle( 'Talk:Community Wishlist/W123' ),
				],
				[],
			],
		];
	}

	public function testBeforeDisplayNoArticleText(): void {
		$handler = $this->getHandler( [
			WishlistConfig::WISH_PAGE_PREFIX => 'Community Wishlist/W',
			WishlistConfig::FOCUS_AREA_PAGE_PREFIX => 'Community Wishlist/FA',
		] );
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

	public function testOutputPageParserOutput(): void {
		$testTitle = $this->makeMockTitle( 'Community Wishlist/W123', [ 'namespace' => NS_TALK ] );
		$testTitle->method( 'isTalkPage' )->willReturn( true );
		$wishStore = $this->createNoOpMock( WishStore::class, [ 'get', 'entityType' ] );
		$wish = $this->createNoOpMock( Wish::class, [ 'getTitle', 'getPage', 'getProposer' ] );
		$wish->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( 'Test wish title' );
		$wish->expects( $this->exactly( 2 ) )
			->method( 'getPage' )
			->willReturn( $testTitle );
		$wish->expects( $this->once() )
			->method( 'getProposer' )
			->willReturn( $this->createConfiguredMock( User::class, [ 'getName' => 'TestUser' ] ) );
		$wishStore->expects( $this->once() )
			->method( 'get' )
			->willReturn( $wish );
		$wishStore->expects( $this->once() )
			->method( 'entityType' )
			->willReturn( 'wish' );
		$language = $this->createNoOpMock( Language::class, [ 'getCode' ] );
		$language->expects( $this->once() )
			->method( 'getCode' )
			->willReturn( 'en' );
		$outputPage = $this->createNoOpMock(
			OutputPage::class,
			[ 'getTitle', 'getLanguage', 'msg', 'prependHTML' ]
		);
		$outputPage->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( $testTitle );
		$outputPage->expects( $this->once() )
			->method( 'getLanguage' )
			->willReturn( $language );
		$outputPage->expects( $this->exactly( 3 ) )
			->method( 'msg' )
			->willReturn( $this->createConfiguredMock( Message::class, [
				'rawParams' => $this->createConfiguredMock( Message::class, [
					'parse' => '[Link to wish]',
				] ),
			] ) );
		$outputPage->expects( $this->once() )
			->method( 'prependHTML' )
			->with( $this->identicalTo(
				'<div class="cdx-message--notice ext-communityrequests-entity-talk-header ' .
				'cdx-message cdx-message--block"><span class="cdx-message__icon"></span>' .
				'<div class="cdx-message__content">[Link to wish]</div></div>'
			) );
		$parserOutput = $this->createNoOpMock( ParserOutput::class );
		$linkRenderer = $this->createNoOpMock( LinkRenderer::class, [ 'makeKnownLink' ] );
		$linkRenderer->expects( $this->once() )
			->method( 'makeKnownLink' )
			->willReturn( '[Link to wish]' );
		$handler = $this->getHandler( [
			WishlistConfig::WISH_PAGE_PREFIX => 'Community Wishlist/W',
			WishlistConfig::FOCUS_AREA_PAGE_PREFIX => 'Community Wishlist/FA',
		], $wishStore, null, $linkRenderer );
		$handler->onOutputPageParserOutput( $outputPage, $parserOutput );
	}

	private function getHandler(
		array $serviceOptions,
		?WishStore $wishStore = null,
		?UserOptionsManager $userOptionsManager = null,
		?LinkRenderer $linkRenderer = null,
	): PageDisplayHooks {
		$config = $this->getConfig( $serviceOptions );
		$extensionRegistry = $this->createNoOpMock( ExtensionRegistry::class, [ 'isLoaded' ] );
		$extensionRegistry->method( 'isLoaded' )->willReturn( false );
		$namespaceInfo = $this->createNoOpMock( NamespaceInfo::class, [ 'getSubjectPage' ] );
		$namespaceInfo->method( 'getSubjectPage' )->willReturnCallback(
			function ( Title $title ) {
				if ( $title->isTalkPage() ) {
					return $this->makeMockTitle( $title->getText() );
				}
				return $title;
			}
		);
		return new PageDisplayHooks(
			$config,
			$wishStore ?? $this->createNoOpMock( WishStore::class ),
			$this->createNoOpMock( FocusAreaStore::class ),
			$this->createNoOpMock( VoteStore::class ),
			$userOptionsManager ?? $this->createNoOpMock( UserOptionsManager::class ),
			$this->getSpecialPageFactory(),
			$namespaceInfo,
			$linkRenderer ?? $this->createNoOpMock( LinkRenderer::class ),
			new NullLogger(),
		);
	}
}
