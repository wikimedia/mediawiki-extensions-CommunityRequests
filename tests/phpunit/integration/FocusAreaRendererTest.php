<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaRenderer
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractRenderer
 * @covers \MediaWiki\Extension\CommunityRequests\RendererFactory
 */
class FocusAreaRendererTest extends MediaWikiIntegrationTestCase {
	use WishlistTestTrait;

	protected function getStore(): FocusAreaStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.FocusAreaStore' );
	}

	/**
	 * Test that a focus area can be created from a wiki page.
	 */
	public function testCreateFocusAreaFromWikiPage(): void {
		$wikitext = <<<END
{{#CommunityRequests: focus-area
|status = in-progress
|title = Test Focus Area
|shortdescription = A brief description of the focus area.
|created = 2023-10-01T12:00:00Z
|baselang = en
|description = Focus on improving the user experience.}}
END;
		$ret = $this->insertPage(
			Title::newFromText( $this->config->getFocusAreaPagePrefix() . '123' ),
			$wikitext,
			NS_MAIN,
			$this->getTestUser()->getUser()
		);

		$focusArea = $this->getStore()->get( $ret['title'], 'en', FocusAreaStore::FETCH_WIKITEXT_TRANSLATED );
		$this->assertSame( $ret['id'], $focusArea->getPage()->getId() );
		$this->assertSame( $this->config->getStatusIdFromWikitextVal( 'in-progress' ), $focusArea->getStatus() );
		$this->assertSame( 'Test Focus Area', $focusArea->getTitle() );
		$this->assertSame( 'A brief description of the focus area.', $focusArea->getShortDescription() );
		$this->assertSame( '2023-10-01T12:00:00Z', $focusArea->getCreated() );
	}

	/**
	 * @dataProvider provideTestErrorCategory
	 */
	public function testErrorCategory( string $wikitext, bool $shouldBeInCategory ): void {
		$ret = $this->insertPage(
			Title::newFromText( $this->config->getFocusAreaPagePrefix() . '123' ),
			$wikitext,
			NS_MAIN
		);
		$categories = array_keys( $ret['title']->getParentCategories() );
		$this->assertContains( 'Category:Community_Wishlist/Focus_areas', $categories );
		if ( $shouldBeInCategory ) {
			$this->assertContains( 'Category:Pages_with_Community_Wishlist_errors', $categories );
		} else {
			$this->assertNotContains( 'Category:Pages_with_Community_Wishlist_errors', $categories );
		}
	}

	// phpcs:disable Generic.Files.LineLength.TooLong
	public static function provideTestErrorCategory(): array {
		return [
			'valid focus area' => [
				'{{#CommunityRequests: focus-area | title=Valid FA | status=under-review | created=2023-10-01T12:00:00Z | baselang=en | description=A valid FA}}',
				false,
			],
			'missing title' => [
				'{{#CommunityRequests: focus-area | status=under-review | created=2023-10-01T12:00:00Z | baselang=en }}',
				true,
			],
			'unknown status' => [
				'{{#CommunityRequests: focus-area | title=Invalid Status FA | status=bogus | created=2023-10-01T12:00:00Z | baselang=en | description=Unknown status}}',
				true,
			],
		];
	}

	// phpcs:enable Generic.Files.LineLength.TooLong

	public function testTrackingCategories(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Translate' );

		$focusAreaTitle = Title::newFromText( $this->config->getFocusAreaPagePrefix() . '456' );
		$this->insertTestFocusArea( $focusAreaTitle, 'fr', [
			FocusArea::PARAM_TITLE => '<translate>Test Focus Area</translate>',
		] );
		$categories = array_keys( $focusAreaTitle->getParentCategories() );
		$this->assertContains( 'Category:Community_Wishlist/Focus_areas', $categories );
		$this->assertNotContains( 'Category:Community_Wishlist/Focus_areas/fr', $categories );
		$this->assertNotContains( 'Category:Pages_with_Community_Wishlist_errors', $categories );
		// Add translation
		$focusAreaDe = $this->insertTestFocusArea( $focusAreaTitle, 'de', [
			FocusArea::PARAM_TITLE => 'Test Focus Area auf Deutsch',
			FocusArea::PARAM_BASE_LANG => 'fr',
		] );
		$categories = array_keys(
			Title::newFromPageReference( $focusAreaDe->getTranslationSubpage() )->getParentCategories()
		);
		$this->assertNotContains( 'Category:Community_Wishlist/Focus_areas', $categories );
		$this->assertContains( 'Category:Community_Wishlist/Focus_areas/de', $categories );
		$this->assertNotContains( 'Category:Pages_with_Community_Wishlist_errors', $categories );
		$this->assertNotContains( 'Category:Community_Wishlist', $categories );
	}
}
