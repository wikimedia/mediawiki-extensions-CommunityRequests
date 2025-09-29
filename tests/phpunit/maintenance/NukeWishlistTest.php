<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Maintenance;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\Maintenance\NukeWishlist;
use MediaWiki\Extension\CommunityRequests\Tests\Integration\WishlistTestTrait;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\CommunityRequests\Maintenance\NukeWishlist
 * @group Database
 */
class NukeWishlistTest extends MaintenanceBaseTestCase {

	use WishlistTestTrait;

	protected function getStore(): WishStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return NukeWishlist::class;
	}

	public function testExecuteWithNoEntitiesOrPages(): void {
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( AbstractWishlistStore::tableName() )
			->assertFieldValue( 0 );
		$this->maintenance->setOption( 'wishes', true );
		$this->maintenance->setOption( 'focus-areas', true );
		$this->maintenance->execute();
		$this->expectOutputString( "0 wish page(s) and their related data have been deleted.\n" );
		$this->expectOutputString( "0 focus-area page(s) and their related data have been deleted.\n" );
	}

	public function testExecuteWishesWithNoWishes(): void {
		$this->getExistingTestPage( 'Community Wishlist/W2' );
		$this->getExistingTestPage( 'Community Wishlist/W50' );
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( AbstractWishlistStore::tableName() )
			->assertFieldValue( 0 );
		$this->maintenance->setOption( 'wishes', true );
		$this->maintenance->execute();
		$this->expectOutputString( "2 wish page(s) and their related data have been deleted.\n" );
	}

	public function testExecuteWishes(): void {
		$this->insertTestWishesAndLegacyWish();
		$this->maintenance->setOption( 'wishes', true );
		$this->maintenance->execute();
		$this->expectOutputString( "2 wish page(s) and their related data have been deleted.\n" );
		$legacyWish = Title::newFromText( 'Community Wishlist/Wishes/Legacy wish' );
		$this->assertTrue( $legacyWish->exists() );
	}

	public function testExecuteLegacy(): void {
		$this->insertTestWishesAndLegacyWish();
		$this->maintenance->setOption( 'wishes', true );
		$this->maintenance->setOption( 'legacy', true );
		$this->maintenance->execute();
		$this->expectOutputString( "1 wish page(s) and their related data have been deleted.\n" );
		$legacyWish = Title::newFromText( 'Community Wishlist/Wishes/Legacy wish' );
		$this->assertFalse( $legacyWish->exists() );
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( AbstractWishlistStore::tableName() )
			->assertFieldValue( 2 );
	}

	public function testExecuteWishAndFocusAreas(): void {
		$this->insertTestWishesAndLegacyWish();
		$this->insertTestFocusArea( 'Community Wishlist/FA1', 'en' );
		$this->insertTestFocusArea( 'Community Wishlist/FA2', 'fr' );
		$this->maintenance->setOption( 'wishes', true );
		$this->maintenance->setOption( 'focus-areas', true );
		$this->maintenance->execute();
		$this->expectOutputString( "2 wish page(s) and their related data have been deleted.\n" );
		$this->expectOutputString( "2 focus-area page(s) and their related data have been deleted.\n" );
		$legacyWish = Title::newFromText( 'Community Wishlist/Wishes/Legacy wish' );
		$this->assertTrue( $legacyWish->exists() );
	}

	private function insertTestWishesAndLegacyWish(): void {
		$this->insertTestWish( 'Community Wishlist/W1', 'en' );
		$this->insertTestWish( 'Community Wishlist/W3', 'fr' );
		$this->insertPage( 'Community Wishlist/Wishes/Legacy wish' );
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( AbstractWishlistStore::tableName() )
			->assertFieldValue( 2 );
	}
}
