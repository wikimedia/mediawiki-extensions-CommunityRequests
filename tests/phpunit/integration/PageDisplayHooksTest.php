<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityRequests\AbstractRenderer;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\Article;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\PageDisplayHooks
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractRenderer
 */
class PageDisplayHooksTest extends MediaWikiIntegrationTestCase {
	use WishlistTestTrait;
	use TempUserTestTrait;

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

	/**
	 * @dataProvider provideMachineTranslationModule
	 */
	public function testMachineTranslationModule(
		string $userType,
		bool $anonEnabled,
		bool $expectLoaded
	): void {
		$this->overrideConfigValues( [
			// Disabled to skip the vote lookup for registered users.
			WishlistConfig::WISH_VOTING_ENABLED => false,
			WishlistConfig::ANON_MACHINE_TRANSLATION_ENABLED => $anonEnabled,
			MainConfigNames::DefaultUserOptions => [ 'usemachinetranslation' => 1 ] +
				$this->getServiceContainer()->getMainConfig()->get( MainConfigNames::DefaultUserOptions ),
		] );
		$this->enableAutoCreateTempUser();

		$context = new RequestContext();
		$context->setTitle( Title::newFromText( $this->config->getWishPagePrefix() . '1' ) );
		$context->setUser( $this->getUserOfType( $userType ) );
		$out = $context->getOutput();
		$this->getServiceContainer()->getHookContainer()->run( 'BeforePageDisplay', [ $out, $context->getSkin() ] );

		$this->assertSame( $expectLoaded, in_array( 'ext.communityrequests.mint', $out->getModules() ) );
	}

	public static function provideMachineTranslationModule(): array {
		return [
			'named user, anon MT disabled' => [ 'named', false, true ],
			'named user, anon MT enabled' => [ 'named', true, true ],
			'anon user, anon MT disabled' => [ 'anon', false, false ],
			'anon user, anon MT enabled' => [ 'anon', true, true ],
			'temp user, anon MT disabled' => [ 'temp', false, false ],
			'temp user, anon MT enabled' => [ 'temp', true, true ],
		];
	}

	private function getUserOfType( string $userType ): User {
		return match ( $userType ) {
			'named' => $this->getTestUser()->getUser(),
			'anon' => $this->getServiceContainer()->getUserFactory()->newAnonymous(),
			'temp' => $this->getServiceContainer()->getTempUserCreator()
				->create( null, new FauxRequest() )->getUser(),
		};
	}
}
