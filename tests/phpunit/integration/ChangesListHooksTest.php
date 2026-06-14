<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\HookHandler\ChangesListHooks;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\RecentChanges\ChangesList;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use MockTitleTrait;
use Psr\Log\NullLogger;
use Wikimedia\HtmlArmor\HtmlArmor;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\ChangesListHooks
 */
class ChangesListHooksTest extends MediaWikiIntegrationTestCase {

	use MockWishlistConfigTrait;
	use MockTitleTrait;

	public function testChangesListInitRows(): void {
		$wishTitle = $this->makeMockTitle( 'Community Wishlist/W1' );
		$handler = $this->getHandler( $wishTitle );

		$changesList = $this->createNoOpMock( ChangesList::class, [ 'getLanguage' ] );
		$changesList->expects( $this->once() )
			->method( 'getLanguage' )
			->willReturn( $this->createConfiguredMock( Language::class, [
				'getCode' => 'en',
			] ) );

		$rows = array_fill( 0, 3, (object)[
			'rc_namespace' => $wishTitle->getNamespace(),
			'rc_title' => $wishTitle->getDBkey(),
		] );

		$handler->onChangesListInitRows( $changesList, $rows );
	}

	public function testOnHtmlPageLinkRendererEnd(): void {
		$wishTitle = $this->makeMockTitle( 'Community Wishlist/W1' );
		$handler = $this->getHandler( $wishTitle );

		$context = RequestContext::getMain();
		$context->setTitle( Title::makeTitle( NS_SPECIAL, 'Contributions' ) );
		$context->setLanguage( $this->getServiceContainer()->getLanguageFactory()->getLanguage( 'en' ) );

		$ret = '';
		$attribs = [];
		$text = null;
		$handler->onHtmlPageLinkRendererEnd(
			$this->createNoOpMock( LinkRenderer::class ),
			$wishTitle,
			true,
			$text,
			$attribs,
			$ret
		);
		$this->assertSame( '<a title="Test wish title">' .
				'<span class="ext-communityrequests-entity-link--label">Test wish title</span> ' .
				'<span class="mw-title ext-communityrequests-entity-link--id" style="font-size: 0.85em;">' .
				'(Community Wishlist/W1)</span></a>',
			HtmlArmor::getHtml( $text )
		);

		// Run again but with an unknown link.
		$text = null;
		$unknownWishTitle = $this->makeMockTitle( 'Community Wishlist/W2' );
		// For good measure; This shouldn't need to be mocked.
		$unknownWishTitle->method( 'exists' )->willReturn( false );
		$handler->onHtmlPageLinkRendererEnd(
			$this->createNoOpMock( LinkRenderer::class ),
			$unknownWishTitle,
			false,
			$text,
			$attribs,
			$ret
		);
		$this->assertNull( $text );

		// And again with predefined $text.
		$text = '(prev)';
		$handler->onHtmlPageLinkRendererEnd(
			$this->createNoOpMock( LinkRenderer::class ),
			$wishTitle,
			true,
			$text,
			$attribs,
			$ret
		);
		$this->assertSame( '(prev)', $text );
	}

	private function getHandler( ?Title $wishTitle = null ): ChangesListHooks {
		$wishTitle ??= $this->makeMockTitle( 'Community Wishlist/W1' );
		$titleFactory = $this->createNoOpMock( TitleFactory::class, [ 'newFromPageReference', 'makeTitle' ] );
		$titleFactory->method( 'newFromPageReference' )
			->willReturn( $wishTitle );
		$titleFactory->method( 'makeTitle' )
			->willReturn( $wishTitle );

		$wish = new Wish(
			$wishTitle,
			'en',
			$this->createNoOpMock( UserIdentity::class, [ 'getName' ] ),
			[ Wish::PARAM_TITLE => 'Test wish title' ]
		);
		$wishStore = $this->createNoOpMock( WishStore::class, [ 'get', 'getAll', 'normalizeArrayValues' ] );
		$wishStore->expects( $this->atMost( 1 ) )
			->method( 'get' )
			->willReturn( $wish );
		$wishStore->expects( $this->atMost( 1 ) )
			->method( 'getAll' )
			->willReturn( [ $wish ] );

		$titleFormatter = $this->createNoOpMock( TitleFormatter::class, [ 'getFullText' ] );
		$titleFormatter->expects( $this->atMost( 1 ) )
			->method( 'getFullText' )
			->willReturn( 'Community Wishlist/W1' );

		return new ChangesListHooks(
			$this->getConfig(),
			$wishStore,
			$this->createNoOpMock( FocusAreaStore::class ),
			$titleFactory,
			$titleFormatter,
			WANObjectCache::newEmpty(),
			new NullLogger(),
		);
	}
}
