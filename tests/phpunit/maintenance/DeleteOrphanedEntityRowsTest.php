<?php

namespace MediaWiki\Extension\CommunityRequests\Tests\Maintenance;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\Maintenance\DeleteOrphanedEntityRows;
use MediaWiki\Extension\CommunityRequests\Tests\Integration\WishlistTestTrait;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\CommunityRequests\Maintenance\DeleteOrphanedEntityRows
 * @group Database
 */
class DeleteOrphanedEntityRowsTest extends MaintenanceBaseTestCase {

	use WishlistTestTrait;

	/** @inheritDoc */
	protected function getStore(): AbstractWishlistStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return DeleteOrphanedEntityRows::class;
	}

	/**
	 * To be removed after T406059 is resolved,
	 * or force-delete a row in the page table instead of using DeletePage.
	 */
	public function testExecuteWithBug(): void {
		// Create a few wishes.
		$wish1 = $this->insertTestWish();
		$wish2 = $this->insertTestWish();

		// Delete W1.
		$wikiPage1 = $this->getServiceContainer()->getWikiPageFactory()
			->newFromID( $wish1->getPage()->getId() );
		$this->assertTrue( $wikiPage1->exists() );
		$this->getDb()->delete(
			'page',
			[ 'page_id' => $wikiPage1->getId() ],
			__METHOD__
		);

		// Count is wrong now.
		$this->assertSame( 2, $this->getStore()->getCount() );

		// Test the count.
		$this->maintenance->setOption( 'dry-run', true );
		$this->maintenance->execute();
		// XXX: $this->expectOutputString() does not work here for some reason.
		$this->assertSame(
			"1 orphaned rows would be deleted.\n",
			$this->getActualOutput()
		);

		// Test the actual deletion.
		$this->maintenance->setOption( 'dry-run', false );
		$this->maintenance->execute();
		$this->assertStringContainsString(
			"1 orphaned rows found. Deleting...\n1 orphaned rows deleted out of 1 found.\n",
			$this->getActualOutput()
		);

		// Verify the deleted wish is gone, and the others remain.
		$this->assertNull( $this->getStore()->get( $wish1->getPage() ) );
		$this->assertNotNull( $this->getStore()->get( $wish2->getPage() ) );
		$this->assertSame( 1, $this->getStore()->getCount() );
	}
}
