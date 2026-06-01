<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\HookHandler\ChangesListHooks;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Language\Language;
use MediaWiki\Pager\ContributionsPager;
use MediaWiki\RecentChanges\ChangesList;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;
use MockTitleTrait;
use Psr\Log\NullLogger;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\ChangesListHooks
 * @covers \MediaWiki\Extension\CommunityRequests\WishlistEntityTrait
 */
class ChangesListHooksTest extends MediaWikiUnitTestCase {

	use MockWishlistConfigTrait;
	use MockTitleTrait;

	public function testOnChangesListInsertArticleLink(): void {
		$wishTitle = $this->makeMockTitle( 'Community Wishlist/W1' );
		$handler = $this->getHandler( $wishTitle );

		$changesList = $this->createNoOpMock( ChangesList::class, [ 'getLanguage', 'msg' ] );
		$changesList->expects( $this->once() )
			->method( 'getLanguage' )
			->willReturn( $this->createConfiguredMock( Language::class, [
				'getCode' => 'en',
			] ) );
		$changesList->expects( $this->once() )
			->method( 'msg' )
			->willReturnCallback( [ new FakeQqxMessageLocalizer(), 'msg' ] );

		$rc = $this->createNoOpMock( RecentChange::class, [ 'getPage' ] );
		$rc->expects( $this->once() )
			->method( 'getPage' )
			->willReturn( $wishTitle->toPageIdentity() );

		$articleLink = '';
		$s = '';
		$handler->onChangesListInsertArticleLink(
			$changesList,
			$articleLink,
			$s,
			$rc,
			false,
			false
		);

		$this->assertSame(
			'<a title="Test wish title"><span class="ext-communityrequests-entity-link--label">Test wish title</span>' .
			' <span class="mw-title ext-communityrequests-entity-link--id" style="font-size: 0.85em;">' .
			'(parentheses: Community Wishlist/W1)</span></a>',
			$articleLink
		);
	}

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

	public function testOnContributionsLineEnding(): void {
		$wishTitle = $this->makeMockTitle( 'Community Wishlist/W1' );
		$handler = $this->getHandler( $wishTitle );

		$pager = $this->createNoOpMock(
			ContributionsPager::class,
			[ 'getLanguage', 'getTemplateParams', 'getProcessedTemplate', 'msg' ]
		);
		$pager->expects( $this->once() )
			->method( 'getLanguage' )
			->willReturn( $this->createConfiguredMock( Language::class, [ 'getCode' => 'en' ] ) );
		$pager->expects( $this->once() )
			->method( 'getTemplateParams' )
			->willReturn( [] );
		$pager->expects( $this->once() )
			->method( 'getProcessedTemplate' )
			->willReturnCallback( static fn ( $templateParams ) => $templateParams['articleLink'] );
		$pager->expects( $this->once() )
			->method( 'msg' )
			->willReturnCallback( [ new FakeQqxMessageLocalizer(), 'msg' ] );
		$ret = '';
		$row = (object)[
			'page_id' => $wishTitle->getArticleID(),
			'page_namespace' => $wishTitle->getNamespace(),
			'page_title' => $wishTitle->getDBkey(),
		];
		$classes = [];
		$attribs = [];

		$handler->onContributionsLineEnding( $pager, $ret, $row, $classes, $attribs );

		$this->assertSame(
			'<a title="Test wish title"><span class="ext-communityrequests-entity-link--label">Test wish title</span>' .
			' <span class="mw-title ext-communityrequests-entity-link--id" style="font-size: 0.85em;">' .
			'(parentheses: Community Wishlist/W1)</span></a>',
			$ret
		);
	}

	public function testOnContributionsLineEndingDeletedPage(): void {
		$wishTitle = $this->makeMockTitle( 'Community Wishlist/W1', [ 'id' => 0 ] );
		$handler = $this->getHandler( $wishTitle );
		$pager = $this->createNoOpMock( ContributionsPager::class );
		$ret = 'Original contents';
		// Now page_id as we're simulating a deleted page.
		$row = (object)[
			'page_namespace' => $wishTitle->getNamespace(),
			'page_title' => $wishTitle->getDBkey(),
		];
		$classes = [];
		$attribs = [];

		$handler->onContributionsLineEnding( $pager, $ret, $row, $classes, $attribs );
		$this->assertSame( 'Original contents', $ret );
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
