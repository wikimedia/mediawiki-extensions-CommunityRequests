<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use InvalidArgumentException;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Tests\CommunityRequestsIntegrationTestCase;
use MediaWiki\Title\Title;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CommunityRequests
 * @group Database
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore
 */
class FocusAreaStoreTest extends CommunityRequestsIntegrationTestCase {

	protected function getStore(): FocusAreaStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.FocusAreaStore' );
	}

	/**
	 * @covers ::save
	 * @covers ::get
	 */
	public function testSaveAndGetFocusArea(): void {
		ConvertibleTimestamp::setFakeTime( '2025-01-23T00:00:00Z' );
		$page = $this->getExistingTestPage( 'Community Wishlist/Focus areas/FA123' );
		$focusArea = new FocusArea(
			$page,
			'en',
			[
				'shortDescription' => 'Test focus area',
				'status' => 'blocked',
				'title' => 'Test Focus Area',
				'created' => '2025-01-01T00:00:00Z',
				'voteCount' => 42,
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

	/**
	 * @covers ::save
	 */
	public function testSaveWishWithNoPage(): void {
		$fauxPage = Title::newFromText( 'Community Wishlist/Wishes/W111' );
		$wish = new FocusArea(
			$fauxPage,
			'en',
			[]
		);
		$this->expectException( InvalidArgumentException::class );
		$this->getStore()->save( $wish );
	}

	/**
	 * @covers ::save
	 */
	public function testSaveWithNoCreationDate(): void {
		$wish = new FocusArea(
			Title::newFromText( 'Community Wishlist/Wishes/W123' ),
			'en',
			[ 'created' => null ]
		);
		$this->expectException( InvalidArgumentException::class );
		$this->getStore()->save( $wish );
	}
}
