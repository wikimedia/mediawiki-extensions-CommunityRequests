<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Maintenance;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\User;
use Throwable;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IMaintainableDatabase;
use Wikimedia\Rdbms\LikeValue;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Maintenance script to delete all wishes, focus areas, and related data
 * and artifacts from the wiki. This is for development purposes only.
 */
class NukeWishlist extends Maintenance {

	private IMaintainableDatabase $dbw;
	private WishlistConfig $config;
	private UltimateAuthority $authority;
	private bool $nukeWishes = false;
	private bool $nukeFocusAreas = false;

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 100 );
		$this->addDescription( 'Delete all wishlist data and related artifacts.' );
		$this->addOption( 'wishes', 'Only delete wishes and related data.' );
		$this->addOption( 'focus-areas', 'Only delete focus areas and related data.' );
		$this->requireExtension( 'CommunityRequests' );
	}

	/** @inheritDoc */
	public function execute() {
		$this->dbw = $this->getServiceContainer()
			->getDBLoadBalancer()
			->getMaintenanceConnectionRef( DB_PRIMARY );
		$this->config = $this->getServiceContainer()->get( 'CommunityRequests.WishlistConfig' );
		$this->authority = new UltimateAuthority(
			User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] )
		);

		if ( $this->hasOption( 'wishes' ) ) {
			$this->nukeWishes = true;
			$this->nukeWishes();
		} elseif ( $this->hasOption( 'focus-areas' ) ) {
			$this->nukeFocusAreas = true;
			$this->nukeFocusAreas();
		} else {
			$this->nukeWishes = true;
			$this->nukeFocusAreas = true;
			$this->nukeWishes();
			$this->nukeFocusAreas();
		}
		$this->truncateTables();

		$this->output( "Nuke wishlist operation completed successfully.\n" );
	}

	private function nukeWishes(): void {
		/** @var WishStore $wishStore */
		$wishStore = $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
		$prefix = $this->getServiceContainer()->getTitleParser()->parseTitle( $this->config->getWishPagePrefix() );

		$this->output( "Deleting all wishes and related data...\n" );
		$this->deletePagesWithPrefix( $prefix, $wishStore );
	}

	private function nukeFocusAreas(): void {
		/** @var FocusAreaStore $focusAreaStore */
		$focusAreaStore = $this->getServiceContainer()->get( 'CommunityRequests.FocusAreaStore' );
		$prefix = $this->getServiceContainer()->getTitleParser()->parseTitle( $this->config->getFocusAreaPagePrefix() );

		$this->output( "Deleting all focus areas and related data...\n" );
		$this->deletePagesWithPrefix( $prefix, $focusAreaStore );
	}

	private function truncateTables(): void {
		$this->beginTransactionRound( __METHOD__ );
		$tables = [];

		if ( $this->nukeWishes ) {
			$tables[] = 'communityrequests_wishes';
			$tables[] = 'communityrequests_wishes_translations';
			$tables[] = 'communityrequests_projects';
			$tables[] = 'communityrequests_phab_tasks';
			$this->resetIdCounter( 'wish' );
		}
		if ( $this->nukeFocusAreas ) {
			$tables[] = 'communityrequests_focus_areas';
			$tables[] = 'communityrequests_focus_areas_translations';
			$this->resetIdCounter( 'focus-area' );
		}

		foreach ( $tables as $table ) {
			$this->dbw->truncateTable( $table, __METHOD__ );
			$this->output( "Truncated table: $table\n" );
		}
		$this->commitTransactionRound( __METHOD__ );
	}

	private function resetIdCounter( string $type ): void {
		$this->dbw->newUpdateQueryBuilder()
			->table( 'communityrequests_counters' )
			->set( [ 'crc_value' => 0 ] )
			->where( [ 'crc_type' => $type === 'wish' ? IdGenerator::TYPE_WISH : IdGenerator::TYPE_FOCUS_AREA ] )
			->caller( __METHOD__ )
			->execute();
		$this->output( "Reset ID counters for $type.\n" );
	}

	private function deletePagesWithPrefix( TitleValue $prefix, AbstractWishlistStore $store ): void {
		$entityName = $store->entityType();
		$qb = $this->dbw->newSelectQueryBuilder()
			->select( [ 'page_id', 'page_namespace', 'page_title' ] )
			->from( 'page' )
			->where( [
				'page_namespace' => $prefix->getNamespace(),
				$this->dbw->expr(
					'page_title',
					IExpression::LIKE,
					new LikeValue( $prefix->getDBkey(), $this->dbw->anyString() )
				)
			] )
			->caller( __METHOD__ );

		$count = 0;
		do {
			$row = $qb->fetchRow();
			if ( $row === false ) {
				break;
			}

			$page = PageIdentityValue::localIdentity( (int)$row->page_id, (int)$row->page_namespace, $row->page_title );
			$entity = null;
			try {
				$entity = $store->get( $page );
			} catch ( Throwable $e ) {
				$this->output(
					"Failed to retrieve $entityName for page {$page->getDBkey()}: " . $e->getMessage() . "\n"
				);
			}

			$this->output( "Deleting $entityName page: {$page->getDBkey()}\n" );

			$deletePage = $this->getServiceContainer()
				->getDeletePageFactory()
				->newDeletePage( $page, $this->authority );
			$status = $deletePage->deleteIfAllowed( 'CommunityRequests nukeWishlist maintenance script' );
			if ( !$status->isOK() ) {
				$this->fatalError(
					"Failed to delete page {$page->getDBkey()}: " .
					wfMessage( $status->getMessages()[0] )->text()
				);
			}

			if ( $entity ) {
				$store->delete( $entity );
			} else {
				$this->output( "No $entityName found for page {$page->getDBkey()}, skipping related data deletion.\n" );
			}

			$count++;
			if ( $count % $this->getBatchSize() === 0 ) {
				$this->output( "Deleted $count $entityName pages so far...\n" );
				$this->commitTransactionRound( __METHOD__ );
			}
		} while ( true );

		$this->output( "$count $entityName page(s) and their related data have been deleted.\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = NukeWishlist::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
