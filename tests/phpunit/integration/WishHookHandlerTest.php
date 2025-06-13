<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Test\Unit;

use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group CommunityRequests
 * @group Database
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\HookHandler\WishHookHandler
 */
class WishHookHandlerTest extends MediaWikiIntegrationTestCase {

	private WishlistConfig $config;
	private WishStore $wishStore;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->getServiceContainer()->get( 'CommunityRequests.WishlistConfig' );
		$this->wishStore = $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	/**
	 * Test that a wish can be created from a wiki page.
	 *
	 * @covers ::renderWish
	 * @covers ::onLinksUpdateComplete
	 * @covers ::onBeforePageDisplay
	 */
	public function testCreateWishFromWikiPage() {
		$wikitext = <<<END
<wish
	title="Test Wish"
	status="submitted"
	type="change"
	description="This is a [[test]] {{wish}}."
	created="2023-10-01T12:00:00Z"
></wish>
END;
		$user = $this->getTestUser()->getUser();
		$ret = $this->insertPage(
			Title::newFromText( $this->config->getWishPagePrefix() . '123' ),
			$wikitext,
			NS_MAIN,
			$user
		);

		$wish = $this->wishStore->getWish( $ret[ 'title' ] );
		$this->assertSame( $ret[ 'id' ], $wish->getPage()->getId() );
		$this->assertSame( 'Test Wish', $wish->getTitle() );
		$this->assertSame( $this->config->getStatusIdFromWikitextVal( 'submitted' ), $wish->getStatus() );
		$this->assertSame( $this->config->getWishTypeIdFromWikitextVal( 'change' ), $wish->getType() );
		$this->assertSame( $user->getName(), $wish->getProposer()->getName() );
		$this->assertSame( '2023-10-01T12:00:00Z', $wish->getCreated() );
	}
}
