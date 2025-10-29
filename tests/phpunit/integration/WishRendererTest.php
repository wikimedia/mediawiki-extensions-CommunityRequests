<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Page\WikiPageFactory;
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

	protected function getWikiPageFactory(): WikiPageFactory {
		return $this->getServiceContainer()->getWikiPageFactory();
	}

	/**
	 * Test that a wish can be created from a wiki page.
	 */
	public function testCreateWishFromWikiPage(): void {
		$user = $this->getTestUser()->getUser();
		$wish = $this->insertTestWish( null, 'en', [
			Wish::PARAM_TYPE => 'change',
			Wish::PARAM_STATUS => 'in-progress',
			Wish::PARAM_TITLE => 'Test Wish',
			Wish::PARAM_DESCRIPTION => 'This is a [[test]] {{wish}}.',
			Wish::PARAM_TAGS => 'multimedia',
			Wish::PARAM_CREATED => '2023-10-01T12:00:00Z',
			Wish::PARAM_PROPOSER => $user->getName(),
		] );

		$this->assertSame( 'Test Wish', $wish->getTitle() );
		$this->assertSame( $this->config->getStatusIdFromWikitextVal( 'in-progress' ), $wish->getStatus() );
		$this->assertSame( $this->config->getWishTypeIdFromWikitextVal( 'change' ), $wish->getType() );
		$this->assertSame( [ $this->config->getTagIdFromWikitextVal( 'multimedia' ) ], $wish->getTags() );
		$this->assertSame( $user->getName(), $wish->getProposer()->getName() );
		$this->assertSame( '2023-10-01T12:00:00Z', $wish->getCreated() );
	}

	public function testCategories(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Translate' );

		$wishTitle = Title::newFromText( $this->config->getWishPagePrefix() . '123' );
		$this->insertTestWish( $wishTitle, 'fr', [
			Wish::PARAM_TITLE => '<translate>Test title</translate>',
			Wish::PARAM_TAGS => 'multimedia,notifications',
		] );
		$categories = array_keys( $wishTitle->getParentCategories() );
		$this->assertContains( 'Category:Community_Wishlist/Wishes', $categories );
		$this->assertNotContains( 'Category:Community_Wishlist/Wishes/fr', $categories );
		$this->assertContains( 'Category:Community_Wishlist/Wishes/Multimedia_and_Commons', $categories );
		$this->assertContains( 'Category:Community_Wishlist/Wishes/Notifications', $categories );
		$this->assertNotContains( 'Category:Pages_with_Community_Wishlist_errors', $categories );
		$this->assertNotContains( 'Category:Community_Wishlist', $categories );
		// Add translation
		$wishDe = $this->insertTestWish( $wishTitle, 'de', [
			Wish::PARAM_TITLE => 'Test title auf Deutsch',
			Wish::PARAM_TAGS => 'multimedia,notifications',
			Wish::PARAM_BASE_LANG => 'fr',
		] );
		$categories = array_keys(
			Title::newFromPageReference( $wishDe->getTranslationSubpage() )->getParentCategories()
		);
		$this->assertNotContains( 'Category:Community_Wishlist/Wishes', $categories );
		$this->assertContains( 'Category:Community_Wishlist/Wishes/de', $categories );
		$this->assertContains( 'Category:Community_Wishlist/Wishes/Multimedia_and_Commons/de', $categories );
		$this->assertContains( 'Category:Community_Wishlist/Wishes/Notifications/de', $categories );
		$this->assertNotContains( 'Category:Community_Wishlist', $categories );

		// Add new wish with a missing title
		$invalidTitle = Title::newFromText( $this->config->getWishPagePrefix() . '124' );
		$this->insertTestWish( $invalidTitle, 'en', [ Wish::PARAM_TITLE => '' ] );
		$categories = array_keys( $invalidTitle->getParentCategories() );
		$this->assertContains( 'Category:Community_Wishlist/Wishes', $categories );
		$this->assertContains( 'Category:Pages_with_Community_Wishlist_errors', $categories );
		$this->assertNotContains( 'Category:Community_Wishlist', $categories );
	}

	/**
	 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks::onParserAfterTidy
	 * @covers \MediaWiki\Extension\CommunityRequests\AbstractRenderer::getVotingSection
	 */
	public function testVoteCountRendering() {
		$wish = $this->insertTestWish(
			'Community Wishlist/W123',
			'en',
			[ 'title' => '<translate>Test wish</translate>', Wish::PARAM_STATUS => 'in-progress' ]
		);
		$this->insertPage(
			$wish->getPage()->getDBkey() . $this->config->getVotesPageSuffix(),
			"{{#CommunityRequests:vote|username=TestUser1|timestamp=2023-10-01T12:00:00Z|comment=First vote}}\n" .
				"{{#CommunityRequests:vote|username=TestUser2|timestamp=2023-10-01T12:00:00Z|comment=Second vote}}\n"
		);
		$wikiPage = $this->getWikiPageFactory()->newFromTitle( $wish->getPage() );
		$wikiPage->updateParserCache();
		$parserOutput = $wikiPage->getParserOutput();
		$this->assertStringContainsString( '<b>2 supporters</b>', $parserOutput->getRawText() );

		$wishDe = $this->insertTestWish(
			'Community Wishlist/W123',
			'de',
			[ Wish::PARAM_BASE_LANG => 'en', Wish::PARAM_STATUS => 'in-progress' ]
		);
		$wikiPageDe = $this->getWikiPageFactory()->newFromTitle(
			Title::newFromPageReference( $wishDe->getTranslationSubpage() )
		);
		$wikiPageDe->updateParserCache();
		$parserOutputDe = $wikiPageDe->getParserOutput();
		$this->assertStringContainsString( '<b>2 Unterstützer</b>', $parserOutputDe->getRawText() );
	}

	public function testChangePageLanguage(): void {
		$wish = $this->insertTestWish( 'Community Wishlist/W123', 'fr' );
		$this->assertSame( 'fr', Title::newFromPageIdentity( $wish->getPage() )->getPageLanguage()->getCode() );
	}

	public function testRenderAfterVotesPageCreation(): void {
		$wish = $this->insertTestWish( 'Community Wishlist/W123', 'en', [
			Wish::PARAM_STATUS => 'prioritized',
		] );
		$this->insertPage(
			$wish->getPage()->getDBkey() . $this->config->getVotesPageSuffix(),
			'{{#CommunityRequests:vote|username=TestUser1|timestamp=2023-10-01T12:00:00Z' .
			"|comment=The very first vote!}}\n"
		);
		$wikiPage = $this->getWikiPageFactory()->newFromTitle( $wish->getPage() );
		$parserText = $wikiPage->getParserOutput()->getContentHolderText();
		$this->assertStringContainsString( 'The very first vote!', $parserText );
	}

	public function testInvalidProposer(): void {
		$wishTitle = Title::newFromText( 'Community Wishlist/W123' );
		$wish = $this->insertTestWish( $wishTitle, 'en', [
			Wish::PARAM_PROPOSER => 'NonExistentUser',
		] );
		$this->assertNull( $wish );
		$wikiPage = $this->getWikiPageFactory()->newFromTitle( $wishTitle );
		$parserText = $wikiPage->getParserOutput()->getContentHolderText();
		$this->assertStringContainsString( '"NonExistentUser" is not a valid user.', $parserText );
		$this->assertContains(
			'Category:Pages_with_Community_Wishlist_errors',
			array_keys( $wishTitle->getParentCategories() )
		);
	}
}
