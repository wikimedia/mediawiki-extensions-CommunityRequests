<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use InvalidArgumentException;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractWishlistStore
 * @covers \MediaWiki\Extension\CommunityRequests\EntityFactory
 */
class FocusAreaStoreTest extends MediaWikiIntegrationTestCase {
	use WishlistTestTrait;

	protected function getStore(): FocusAreaStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.FocusAreaStore' );
	}

	public function testSaveAndGetFocusArea(): void {
		ConvertibleTimestamp::setFakeTime( '2025-01-23T00:00:00Z' );
		$page = $this->getExistingTestPage( 'Community Wishlist/FA123' );
		$focusArea = new FocusArea(
			$page,
			'en',
			[
				FocusArea::PARAM_SHORT_DESCRIPTION => 'Test focus area',
				FocusArea::PARAM_STATUS => 'blocked',
				FocusArea::PARAM_TITLE => 'Test Focus Area',
				FocusArea::PARAM_CREATED => '2025-01-01T00:00:00Z',
				FocusArea::PARAM_VOTE_COUNT => 42,
			]
		);
		$this->getStore()->save( $focusArea );
		$retrievedFocusArea = $this->getStore()->get( $focusArea->getPage(), 'en' );
		$this->assertInstanceOf( FocusArea::class, $retrievedFocusArea );
		$this->assertSame( $page->getId(), $retrievedFocusArea->getPage()->getId() );
		$this->assertSame( '2025-01-01T00:00:00Z', $retrievedFocusArea->getCreated() );
		$this->assertSame( '2025-01-23T00:00:00Z', $retrievedFocusArea->getUpdated() );
		$this->assertSame( 42, $retrievedFocusArea->getVoteCount() );
	}

	public function testSaveWithNoPage(): void {
		$fauxPage = Title::newFromText( 'Community Wishlist/FA111' );
		$wish = new FocusArea(
			$fauxPage,
			'en',
			[]
		);
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Focus area page has not been added to the database yet!' );
		$this->getStore()->save( $wish );
	}

	public function testSaveWishWithNoTitle(): void {
		$title = $this->getExistingTestPage( 'Community Wishlist/FA123' )->getTitle();
		$focusArea = new FocusArea( $title, 'en', [ FocusArea::PARAM_TITLE => '' ] );
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Focus areas must have a title!' );
		$this->getStore()->save( $focusArea );
	}

	public function testSortingWithNonDefaultLang(): void {
		$this->insertTestFocusArea( null, 'en', [ FocusArea::PARAM_TITLE => 'The first!' ] );
		$this->insertTestFocusArea( null, 'es', [ FocusArea::PARAM_TITLE => 'Otra area' ] );
		$allFAs = $this->getStore()->getAll(
			'en',
			FocusAreaStore::titleField(),
			FocusAreaStore::SORT_DESC
		);
		$this->assertSame( 'The first!', $allFAs[0]->getTitle() );
		$this->assertSame( 'Otra area', $allFAs[1]->getTitle() );
	}
}
