<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Maintenance;

use MediaWiki\Extension\CommunityRequests\Maintenance\NukeWishes;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Title\Title;

/**
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\Maintenance\NukeWishes
 * @group Database
 */
class NukeWishesTest extends MaintenanceBaseTestCase {
	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return NukeWishes::class;
	}

	/**
	 * @covers ::execute
	 */
	public function testExecuteForNoWishesOrPages(): void {
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'communityrequests_wishes' )
			->assertFieldValue( 0 );
		$this->maintenance->execute();
		$this->expectOutputRegex( "/0 wishes and their related data have been deleted.\n$/" );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecuteWithNoWishes(): void {
		$this->getExistingTestPage( 'Community Wishlist/Wishes/W2' );
		$this->getExistingTestPage( 'Community Wishlist/Wishes/W50' );
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'communityrequests_wishes' )
			->assertFieldValue( 0 );
		$this->maintenance->execute();
		$this->expectOutputRegex( "/2 wishes and their related data have been deleted.\n$/" );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute(): void {
		$proposer = $this->getTestUser()->getUser();
		$this->insertPage(
			Title::newFromText( 'Community Wishlist/Wishes/W1' ),
			"<wish title=\"My first wish\" proposer=\"{$proposer->getName()}\" " .
				"created='2025-06-25T12:59:59Z' baselang=\"en\" />"
		);
		$this->insertPage(
			Title::newFromText( 'Community Wishlist/Wishes/W2' ),
			"<wish title=\"My second wish\" proposer=\"{$proposer->getName()}\" " .
				"created='2025-06-25T12:59:59Z' baselang=\"en\" />"
		);
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'communityrequests_wishes' )
			->assertFieldValue( 2 );
		$this->maintenance->execute();
		$this->expectOutputRegex( "/2 wishes and their related data have been deleted.\n$/" );
	}
}
