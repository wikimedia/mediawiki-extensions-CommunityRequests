<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Maintenance;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\Maintenance\NukeWishlist;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\CommunityRequests\Maintenance\NukeWishlist
 * @group Database
 */
class NukeWishlistTest extends MaintenanceBaseTestCase {
	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return NukeWishlist::class;
	}

	public function testExecuteWithNoEntitiesOrPages(): void {
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( AbstractWishlistStore::tableName() )
			->assertFieldValue( 0 );
		$this->maintenance->execute();
		$this->expectOutputString( "0 wish page(s) and their related data have been deleted.\n" );
		$this->expectOutputString( "0 focus-area page(s) and their related data have been deleted.\n" );
	}

	public function testExecuteWithNoWishes(): void {
		$this->getExistingTestPage( 'Community Wishlist/Wishes/W2' );
		$this->getExistingTestPage( 'Community Wishlist/Wishes/W50' );
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( AbstractWishlistStore::tableName() )
			->assertFieldValue( 0 );
		$this->maintenance->execute();
		$this->expectOutputString( "2 wish page(s) and their related data have been deleted.\n" );
	}

	public function testExecute(): void {
		$proposer = $this->getTestUser()->getUser()->getName();
		$this->insertPage(
			Title::newFromText( 'Community Wishlist/Wishes/W1' ),
			"{{#CommunityRequests: wish | title=My first wish | proposer = $proposer " .
				"| created=2025-06-25T12:59:59Z | baselang=en}}"
		);
		$this->insertPage(
			Title::newFromText( 'Community Wishlist/Wishes/W2' ),
			"{{#CommunityRequests: wish | title=My second wish | proposer=$proposer " .
				"| created=2025-06-25T12:59:59Z | baselang=en}}"
		);
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( AbstractWishlistStore::tableName() )
			->assertFieldValue( 2 );
		$this->maintenance->execute();
		$this->expectOutputString( "2 wish page(s) and their related data have been deleted.\n" );
	}
}
