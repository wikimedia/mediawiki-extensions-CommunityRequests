<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Maintenance;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Maintenance\Maintenance;
use Wikimedia\Rdbms\IDatabase;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Maintenance script to delete orphaned rows in communityrequests_entities
 */
class DeleteOrphanedEntityRows extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Delete orphaned rows in communityrequests_entities' );
		$this->addOption( 'dry-run', 'Dry run only, do not perform any actual deletions' );
	}

	public function execute(): void {
		$mwDbr = $this->getDB( DB_REPLICA );
		$crDbw = $this->getServiceContainer()
			->getConnectionProvider()
			->getPrimaryDatabase( 'virtual-communityrequests' );
		$dryRun = (bool)$this->getOption( 'dry-run', false );

		$entityPageIds = $this->getEntityPageIds( $crDbw );

		$select = $mwDbr->newSelectQueryBuilder()
			->select( 'page_id' )
			->from( 'page' )
			->where( [ 'page_id' => $entityPageIds ] )
			->caller( __METHOD__ );
		$existingPageIds = $select->fetchFieldValues();
		$orphanedEntityPageIds = array_diff( $entityPageIds, $existingPageIds );
		$orphanedCount = count( $orphanedEntityPageIds );
		if ( $dryRun ) {
			$this->output( "$orphanedCount orphaned rows would be deleted.\n" );
			return;
		}

		$this->output( $orphanedCount . " orphaned rows found. " );
		if ( $orphanedCount === 0 ) {
			$this->output( "Nothing to do.\n" );
			return;
		}
		$this->output( "Deleting...\n" );

		$crDbw->newDeleteQueryBuilder()
			->delete( AbstractWishlistStore::tableName() )
			->where( [ AbstractWishlistStore::pageField() => $orphanedEntityPageIds ] )
			->caller( __METHOD__ )
			->execute();
		$rowsDeleted = $crDbw->affectedRows();
		$this->output( "$rowsDeleted orphaned rows deleted out of $orphanedCount found.\n" );

		$crDbw->newDeleteQueryBuilder()
			->deleteFrom( AbstractWishlistStore::translationsTableName() )
			->where( [ AbstractWishlistStore::translationForeignKey() => $orphanedEntityPageIds ] )
			->execute();
		$translationsDeleted = $crDbw->affectedRows();
		$this->output( "$translationsDeleted translation rows deleted.\n" );

		$crDbw->newDeleteQueryBuilder()
			->deleteFrom( AbstractWishlistStore::tagsTableName() )
			->where( [ 'crtg_entity' => $orphanedEntityPageIds ] )
			->execute();
		$tagsDeleted = $crDbw->affectedRows();
		$this->output( "$tagsDeleted tag rows deleted.\n" );

		$this->output( "Done.\n" );
	}

	private function getEntityPageIds( IDatabase $dbr ): array {
		return $dbr->newSelectQueryBuilder()
			->select( AbstractWishlistStore::pageField() )
			->from( AbstractWishlistStore::tableName() )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}
}

// @codeCoverageIgnoreStart
$maintClass = DeleteOrphanedEntityRows::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
