<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\HookHandler\ChangesListHooks;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Language\Language;
use MediaWiki\RecentChanges\ChangesList;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;
use MockTitleTrait;
use Psr\Log\NullLogger;

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
		$wish = new Wish(
			$wishTitle,
			'en',
			$this->createNoOpMock( UserIdentity::class ),
			[ Wish::PARAM_TITLE => 'Test wish title' ]
		);
		$wishStore = $this->createNoOpMock( WishStore::class, [ 'get' ] );
		$wishStore->expects( $this->once() )
			->method( 'get' )
			->willReturn( $wish );

		$titleFormatter = $this->createNoOpMock( TitleFormatter::class, [ 'getFullText' ] );
		$titleFormatter->expects( $this->once() )
			->method( 'getFullText' )
			->willReturn( 'Community Wishlist/W1' );

		$handler = new ChangesListHooks(
			$this->getConfig(),
			$wishStore,
			$this->createNoOpMock( FocusAreaStore::class ),
			$titleFormatter,
			new NullLogger(),
		);

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
}
