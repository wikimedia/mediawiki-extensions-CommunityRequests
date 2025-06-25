<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Maintenance;

use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\User\User;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Maintenance script to delete all wishes and related data and artifacts from the wiki.
 * This is for development purposes only.
 *
 * @todo Perform safe deletion of wish pages using Extension:Translate.
 */
class NukeWishes extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 100 );
		$this->addDescription( 'Delete all wishes and related data.' );
		$this->requireExtension( 'CommunityRequests' );
	}

	/** @inheritDoc */
	public function execute() {
		$conn = $this->getServiceContainer()->getConnectionProvider();
		$dbw = $conn->getPrimaryDatabase();
		$services = $this->getServiceContainer();
		$performer = new UltimateAuthority(
			User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] )
		);
		/** @var WishStore $wishStore */
		$wishStore = $services->get( 'CommunityRequests.WishStore' );
		/** @var WishlistConfig $config */
		$config = $services->get( 'CommunityRequests.WishlistConfig' );

		$prefix = $services->getTitleParser()->parseTitle( $config->getWishPagePrefix() );

		// Query for a title prefix in the event the CommunityRequests tables are corrupted.
		$qb = $dbw->newSelectQueryBuilder()
			->select( [ 'page_id', 'page_namespace', 'page_title' ] )
			->from( 'page' )
			->where( [
				'page_namespace' => $prefix->getNamespace(),
				$dbw->expr(
					'page_title',
					IExpression::LIKE,
					new LikeValue( $prefix->getDBkey(), $dbw->anyString() )
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
			$wish = $wishStore->getWish( $page );

			$this->output( "Deleting wish page: {$page->getDBkey()}\n" );

			$deletePage = $services->getDeletePageFactory()->newDeletePage( $page, $performer );
			$status = $deletePage->deleteIfAllowed( 'CommunityRequests nukeWishes maintenance script' );
			if ( !$status->isOK() ) {
				$this->fatalError(
					"Failed to delete page {$page->getDBkey()}: " .
					wfMessage( $status->getMessages()[ 0 ] )->text()
				);
			}

			if ( $wish ) {
				$wishStore->delete( $wish );
			} else {
				$this->output( "No wish found for page {$page->getDBkey()}, skipping related data deletion.\n" );
			}

			$count++;
			if ( $count % $this->getBatchSize() === 0 ) {
				$this->output( "Deleted $count wish pages so far...\n" );
				$this->commitTransactionRound( __METHOD__ );
			}
		} while ( true );

		// Reset the ID counters.
		$dbw->newUpdateQueryBuilder()
			->table( 'communityrequests_counters' )
			->set( [ 'crc_value' => 0 ] )
			->where( [ 'crc_type' => IdGenerator::TYPE_WISH ] )
			->caller( __METHOD__ )->execute();

		$this->output( "$count wishes and their related data have been deleted.\n" );
	}
}

$maintClass = NukeWishes::class;
require_once RUN_MAINTENANCE_IF_MAIN;
