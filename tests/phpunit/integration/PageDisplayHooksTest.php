<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\AbstractRenderer;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Page\Article;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\PageDisplayHooks
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractRenderer
 */
class PageDisplayHooksTest extends MediaWikiIntegrationTestCase {
	use WishlistTestTrait;

	protected function getStore(): AbstractWishlistStore {
		return $this->store;
	}

	public function testTitleSpanStashedInExtensionData(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );

		$wish = $this->insertTestWish( null, 'en', [ Wish::PARAM_TITLE => 'Test Wish' ] );
		$parserOptions = ParserOptions::newFromAnon();
		$parserOptions->setUseParsoid();
		$parserOutput = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( $wish->getPage() )
			->getParserOutput( $parserOptions );
		$this->assertSame(
			'<span class="ext-communityrequests-wish--title" lang="en" dir="ltr">Test Wish</span>',
			$parserOutput->getExtensionData( AbstractRenderer::EXT_DATA_TITLE_SPAN )
		);
	}

	public function testDisplayTitleOnParsoidViewWithUserLang(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );

		$wish = $this->insertTestWish( null, 'en', [ Wish::PARAM_TITLE => 'Test Wish' ] );
		$title = Title::newFromPageReference( $wish->getPage() );
		// A non-default interface language would previously show the raw page title.
		$this->setUserLang( 'qqx' );
		$this->setTemporaryHook( 'ArticleParserOptions', static function ( $article, $popts ) {
			$popts->setUseParsoid();
		} );

		$article = new Article( $title );
		$article->getContext()->getOutput()->setTitle( $title );
		$article->view();

		$pageTitle = $article->getContext()->getOutput()->getPageTitle();
		$this->assertStringContainsString(
			'<span class="ext-communityrequests-wish--title" lang="en" dir="ltr">Test Wish</span>',
			$pageTitle
		);
		// The ID span localizes to the viewer's language.
		$this->assertStringContainsString(
			"(parentheses: {$title->getPrefixedText()})",
			$pageTitle
		);
	}

	public function testDisplayTitleOnParsoidViewFocusArea(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.FocusAreaStore' );

		$focusArea = $this->insertTestFocusArea( null, 'en', [
			FocusArea::PARAM_TITLE => 'Test Focus Area',
		] );
		$title = Title::newFromPageReference( $focusArea->getPage() );
		$this->setTemporaryHook( 'ArticleParserOptions', static function ( $article, $popts ) {
			$popts->setUseParsoid();
		} );

		$article = new Article( $title );
		$article->getContext()->getOutput()->setTitle( $title );
		$article->view();

		$pageTitle = $article->getContext()->getOutput()->getPageTitle();
		$this->assertStringContainsString( 'ext-communityrequests-focus-area--title', $pageTitle );
		$this->assertStringContainsString( 'Test Focus Area', $pageTitle );
		$this->assertStringContainsString( 'ext-communityrequests-focus-area--id', $pageTitle );
	}

	public function testNoEntityDataLeavesTitleUntouched(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );

		$title = Title::newFromText( $this->config->getWishPagePrefix() . '999' );
		$this->insertPage( $title, 'Just some text, no parser function.' );
		$this->setTemporaryHook( 'ArticleParserOptions', static function ( $article, $popts ) {
			$popts->setUseParsoid();
		} );

		$article = new Article( $title );
		$article->getContext()->getOutput()->setTitle( $title );
		$article->view();

		$this->assertStringNotContainsString(
			'ext-communityrequests',
			$article->getContext()->getOutput()->getPageTitle()
		);
	}
}
