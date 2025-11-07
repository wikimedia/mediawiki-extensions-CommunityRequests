<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\HookHandler\SearchHooks;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Language\Language;
use MediaWiki\Specials\SpecialSearch;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;
use MockTitleTrait;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use RevisionSearchResult;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\SearchHooks
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\WishlistEntityTrait
 */
class SearchHooksTest extends MediaWikiUnitTestCase {

	use MockWishlistConfigTrait;
	use MockTitleTrait;

	protected function tearDown(): void {
		SearchHooks::clearEntityCache();
		parent::tearDown();
	}

	public function testOnShowSearchHitWish(): void {
		$wishTitle = $this->makeMockTitle( 'Community Wishlist/W1' );
		$wish = new Wish(
			$wishTitle,
			'en',
			$this->createNoOpMock( UserIdentity::class ),
			[
				Wish::PARAM_TITLE => 'Test wish title',
				Wish::PARAM_DESCRIPTION => 'Test wish description',
				Wish::PARAM_VOTE_COUNT => 5,
			]
		);
		$wishStore = $this->createNoOpMock( WishStore::class, [ 'get' ] );
		$wishStore->expects( $this->once() )
			->method( 'get' )
			->willReturn( $wish );

		$titleFormatter = $this->createNoOpMock( TitleFormatter::class, [ 'getFullText' ] );
		$titleFormatter->expects( $this->once() )
			->method( 'getFullText' )
			->willReturn( 'Community Wishlist/W1' );

		$handler = new SearchHooks(
			$this->getConfig(),
			$wishStore,
			$this->createNoOpMock( FocusAreaStore::class ),
			new NullLogger(),
			$titleFormatter,
		);

		$searchPage = $this->getMockSpecialSearch();
		$searchPage->expects( $this->exactly( 3 ) )
			->method( 'msg' )
			->willReturnCallback( [ new FakeQqxMessageLocalizer(), 'msg' ] );
		$searchResult = $this->createNoOpMock( RevisionSearchResult::class, [ 'getTitle' ] );
		$searchResult->expects( $this->exactly( 2 ) )
			->method( 'getTitle' )
			->willReturn( $wishTitle );
		$terms = [ "\bFoobar\b" ];
		$size = '881 bytes (174 words)';
		$link = $redirect = $section = $extract = $score = $date = $related = $html = '';

		$handler->onShowSearchHit(
			$searchPage, $searchResult, $terms,
			$link, $redirect, $section,
			$extract, $score, $size,
			$date, $related, $html
		);

		$this->assertSame(
			'<a title="Test wish title"><span class="ext-communityrequests-entity-link--label">Test wish title</span>' .
				' <span class="mw-title ext-communityrequests-entity-link--id" style="font-size: 0.85em;">' .
				'(parentheses: Community Wishlist/W1)</span></a>',
			$link
		);
		$this->assertSame( '881 bytes (174 words)(comma-separator)(communityrequests-vote-count: 5, 5)', $size );
	}

	public function testOnShowSearchHitFocusArea(): void {
		$focusAreaTitle = $this->makeMockTitle( 'Community Wishlist/FA1' );
		$focusArea = new FocusArea(
			$focusAreaTitle,
			'en',
			[
				FocusArea::PARAM_TITLE => 'Test focus area title',
				FocusArea::PARAM_DESCRIPTION => 'Test focus area description',
				FocusArea::PARAM_VOTE_COUNT => 5,
			]
		);
		$focusAreaStore = $this->createNoOpMock( FocusAreaStore::class, [ 'get', 'getWishCounts' ] );
		$focusAreaStore->expects( $this->once() )
			->method( 'get' )
			->willReturn( $focusArea );
		$focusAreaStore->expects( $this->once() )
			->method( 'getWishCounts' )
			->willReturn( 10 );

		$titleFormatter = $this->createNoOpMock( TitleFormatter::class, [ 'getFullText' ] );
		$titleFormatter->expects( $this->once() )
			->method( 'getFullText' )
			->willReturn( 'Community Wishlist/W1' );

		$handler = new SearchHooks(
			$this->getConfig(),
			$this->createNoOpMock( WishStore::class ),
			$focusAreaStore,
			new NullLogger(),
			$titleFormatter,
		);

		$searchPage = $this->getMockSpecialSearch();
		$searchPage->expects( $this->exactly( 5 ) )
			->method( 'msg' )
			->willReturnCallback( [ new FakeQqxMessageLocalizer(), 'msg' ] );
		$searchResult = $this->createNoOpMock( RevisionSearchResult::class, [ 'getTitle' ] );
		$searchResult->expects( $this->exactly( 2 ) )
			->method( 'getTitle' )
			->willReturn( $focusAreaTitle );
		$terms = [ "\bFoobar\b" ];
		$size = '881 bytes (174 words)';
		$link = $redirect = $section = $extract = $score = $date = $related = $html = '';

		$handler->onShowSearchHit(
			$searchPage, $searchResult, $terms,
			$link, $redirect, $section,
			$extract, $score, $size,
			$date, $related, $html
		);

		$this->assertSame(
			'<a title="Test focus area title">' .
				'<span class="ext-communityrequests-entity-link--label">Test focus area title</span> ' .
				'<span class="mw-title ext-communityrequests-entity-link--id" style="font-size: 0.85em;">' .
				'(parentheses: Community Wishlist/W1)</span></a>',
			$link
		);
		$this->assertSame(
			'881 bytes (174 words)' .
				'(comma-separator)(communityrequests-wish-count: 10, 10)' .
				'(comma-separator)(communityrequests-vote-count: 5, 5)',
			$size
		);
	}

	private function getMockSpecialSearch(): MockObject&SpecialSearch {
		$language = $this->createNoOpMock( Language::class, [ 'getCode' ] );
		$language->expects( $this->once() )
			->method( 'getCode' )
			->willReturn( 'en' );
		$context = $this->createNoOpMock( IContextSource::class, [ 'getLanguage', 'getOutput' ] );
		$context->expects( $this->once() )
			->method( 'getLanguage' )
			->willReturn( $language );
		$searchPage = $this->createNoOpMock( SpecialSearch::class, [ 'getContext', 'msg' ] );
		$searchPage->expects( $this->once() )
			->method( 'getContext' )
			->willReturn( $context );
		return $searchPage;
	}
}
