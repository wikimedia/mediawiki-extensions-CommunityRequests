<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\Wish\WishRenderer
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractRenderer
 * @covers \MediaWiki\Extension\CommunityRequests\RendererFactory
 */
class WishRendererTest extends MediaWikiIntegrationTestCase {
	use WishlistTestTrait;

	protected function getStore(): WishStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	/**
	 * Test that a wish can be created from a wiki page.
	 */
	public function testCreateWishFromWikiPage(): void {
		$user = $this->getTestUser()->getUser();
		$wikitext = <<<END
{{#CommunityRequests: wish
|title = Test Wish
|status = open
|type = change
|projects = commons
|created = 2023-10-01T12:00:00Z
|proposer = {$user->getName()}
|baselang = en
|This is a [[test]] {{wish}}.}}
END;
		$ret = $this->insertPage(
			Title::newFromText( $this->config->getWishPagePrefix() . '123' ),
			$wikitext,
			NS_MAIN,
			$user
		);

		$wish = $this->getStore()->get( $ret['title'] );
		$this->assertSame( $ret['id'], $wish->getPage()->getId() );
		$this->assertSame( 'Test Wish', $wish->getTitle() );
		$this->assertSame( $this->config->getStatusIdFromWikitextVal( 'open' ), $wish->getStatus() );
		$this->assertSame( $this->config->getWishTypeIdFromWikitextVal( 'change' ), $wish->getType() );
		$this->assertSame( [ $this->config->getProjectIdFromWikitextVal( 'commons' ) ], $wish->getProjects() );
		$this->assertSame( $user->getName(), $wish->getProposer()->getName() );
		$this->assertSame( '2023-10-01T12:00:00Z', $wish->getCreated() );
	}

	/**
	 * @dataProvider provideTestTrackingCategories
	 */
	public function testTrackingCategories( string $wikitext, bool $shouldBeInCategory ): void {
		$userName = $this->getTestUser()->getUser()->getName();
		$ret = $this->insertPage(
			Title::newFromText( $this->config->getWishPagePrefix() . '123' ),
			str_replace( '$1', $userName, $wikitext ),
			NS_MAIN,
			$this->getTestUser()->getUser()
		);
		$categories = array_keys( $ret['title']->getParentCategories() );
		$this->assertContains( 'Category:Community_Wishlist/Wishes', $categories );
		if ( $shouldBeInCategory ) {
			$this->assertContains( 'Category:Pages_with_Community_Wishlist_errors', $categories );
		} else {
			$this->assertNotContains( 'Category:Pages_with_Community_Wishlist_errors', $categories );
		}
	}

	// phpcs:disable Generic.Files.LineLength.TooLong
	public static function provideTestTrackingCategories(): array {
		return [
			'valid wish' => [
				'{{#CommunityRequests: wish | title=Valid Wish | status=under-review | type=change | projects=commons | created=2023-10-01T12:00:00Z | proposer=$1 | baselang=en | description=A valid wish}}',
				false,
			],
			'missing title' => [
				'{{#CommunityRequests: wish | status=under-review | type=change | projects=commons | created=2023-10-01T12:00:00Z | proposer=$1 | baselang=en |Missing title}}',
				true,
			],
			'unknown status' => [
				'{{#CommunityRequests: wish | title=Invalid Status Wish | status=bogus | type=change | projects=commons | created=2023-10-01T12:00:00Z | proposer=$1 | baselang=en | description=Unknown status}}',
				true,
			],
		];
	}

	/**
	 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks::onParserAfterTidy
	 * @covers \MediaWiki\Extension\CommunityRequests\AbstractRenderer::getVotingSection
	 */
	public function testVoteCountRendering() {
		$wikiPageFactory = $this->getServiceContainer()->getWikiPageFactory();
		$wish = $this->insertTestWish(
			'Community Wishlist/Wishes/W123',
			'en',
			[ 'title' => '<translate>Test wish</translate>' ]
		);
		$this->insertPage(
			$wish->getPage()->getDBkey() . $this->config->getVotesPageSuffix(),
			"{{#CommunityRequests:vote|username=TestUser1|timestamp=2023-10-01T12:00:00Z|comment=First vote}}\n" .
				"{{#CommunityRequests:vote|username=TestUser2|timestamp=2023-10-01T12:00:00Z|comment=Second vote}}\n"
		);
		$wikiPage = $wikiPageFactory->newFromTitle( $wish->getPage() );
		$wikiPage->updateParserCache();
		$parserOutput = $wikiPage->getParserOutput();
		$this->assertStringContainsString( '<b>2 supporters</b>', $parserOutput->getRawText() );

		$wishDe = $this->insertTestWish(
			'Community Wishlist/Wishes/W123',
			'de',
			[ Wish::PARAM_BASE_LANG => 'en' ]
		);
		$wikiPageDe = $wikiPageFactory->newFromTitle(
			Title::newFromPageReference( $wishDe->getTranslationSubpage() )
		);
		$wikiPageDe->updateParserCache();
		$parserOutputDe = $wikiPageDe->getParserOutput();
		$this->assertStringContainsString( '<b>2 Unterstützer</b>', $parserOutputDe->getRawText() );
	}

	// phpcs:enable Generic.Files.LineLength.TooLong

	public function testChangePageLanguage(): void {
		$wish = $this->insertTestWish( 'Community Wishlist/Wishes/W123', 'fr' );
		$this->assertSame( 'fr', Title::newFromPageIdentity( $wish->getPage() )->getPageLanguage()->getCode() );
	}
}
