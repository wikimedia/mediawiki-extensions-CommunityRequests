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
	 */
	public function testCreateWishFromWikiPage(): void {
		$user = $this->getTestUser()->getUser();
		$wikitext = <<<END
<wish
	title="Test Wish"
	status="submitted"
	type="change"
	projects="commons"
	created="2023-10-01T12:00:00Z"
	proposer="{$user->getName()}"
>This is a [[test]] {{wish}}.</wish>
END;
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
		$this->assertSame( [ $this->config->getProjectIdFromWikitextVal( 'commons' ) ], $wish->getProjects() );
		$this->assertSame( $user->getName(), $wish->getProposer()->getName() );
		$this->assertSame( '2023-10-01T12:00:00Z', $wish->getCreated() );
	}

	/**
	 * @dataProvider provideTestTrackingCategories
	 * @covers ::renderWish
	 */
	public function testTrackingCategories( string $wikitext, bool $shouldBeInCategory ): void {
		$userName = $this->getTestUser()->getUser()->getName();
		$ret = $this->insertPage(
			Title::newFromText( $this->config->getWishPagePrefix() . '123' ),
			str_replace( '$1', $userName, $wikitext ),
			NS_MAIN,
			$this->getTestUser()->getUser()
		);
		$categories = array_keys( $ret[ 'title' ]->getParentCategories() );
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
				'<wish title="Valid Wish" status="submitted" type="change" projects="commons" created="2023-10-01T12:00:00Z" proposer="$1">A valid wish</wish>',
				false,
			],
			'missing title' => [
				'<wish status="submitted" type="change" projects="commons" created="2023-10-01T12:00:00Z" proposer="$1">Missing title</wish>',
				true,
			],
			'unknown status' => [
				'<wish title="Invalid Status Wish" status="bogus" type="change" projects="commons" created="2023-10-01T12:00:00Z" proposer="$1">Unknown status</wish>',
				true,
			],
		];
	}

	// phpcs:enable Generic.Files.LineLength.TooLong
}
