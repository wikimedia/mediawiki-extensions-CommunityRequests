<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWikiIntegrationTestCase;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\EntityDataRenderer
 */
class EntityDataRendererTest extends MediaWikiIntegrationTestCase {

	use WishlistTestTrait;

	protected function getStore(): WishStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	public function testRender(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Translate' );

		$this->insertTestWish( 'Community Wishlist/W123', 'en', [
			Wish::PARAM_TITLE => '<translate>Test Wish</translate>',
			Wish::PARAM_STATUS => 'community-opportunity',
			Wish::PARAM_FOCUS_AREA => 'FA1',
			Wish::PARAM_VOTE_COUNT => 42,
		] );
		$this->insertTestWish( 'Community Wishlist/W123', 'fr', [
			Wish::PARAM_TITLE => 'Souhait de test',
			Wish::PARAM_BASE_LANG => 'en',
		] );

		$parser = $this->getServiceContainer()->getParserFactory()->getInstance();
		$contextTitle = $this->insertPage( 'Test page' )['title'];

		$this->assertSame(
			'<p class="error">Missing required field: field</p>',
			$parser->parse( '{{#CommunityRequests:data|id=W123}}',
				$contextTitle, $parser->getOptions() )->getContentHolderText()
		);

		$this->assertSame(
			"<p>Test Wish\n</p>",
			$parser->parse( '{{#CommunityRequests:data|id=W123|field=title}}',
				$contextTitle, $parser->getOptions() )->getContentHolderText()
		);

		$this->assertSame(
			"<p>Souhait de test\n</p>",
			$parser->parse( '{{#CommunityRequests:data|id=W123|field=title|lang=fr}}',
				$contextTitle, $parser->getOptions() )->getContentHolderText()
		);
	}
}
