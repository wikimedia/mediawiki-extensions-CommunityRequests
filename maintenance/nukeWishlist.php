<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Maintenance;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Page\DeletePageFactory;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Title\TitleParser;
use MediaWiki\User\User;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IMaintainableDatabase;
use Wikimedia\Rdbms\IResultWrapper;
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
	private DeletePageFactory $deletePageFactory;
	private TitleParser $titleParser;
	private AbstractWishlistStore $store;
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
		$this->addOption( 'legacy', 'Only delete wishlist pages under the old page prefix '
			. ' instead of $wgCommunityRequestsWishPagePrefix.'
		);
		$this->addOption( 'dry-run', 'Do not actually delete anything, just show what would be done.' );
		$this->requireExtension( 'CommunityRequests' );
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$this->deletePageFactory = $services->getDeletePageFactory();
		$this->titleParser = $services->getTitleParser();
		$this->config = $services->get( 'CommunityRequests.WishlistConfig' );
		$this->dbw = $services->getConnectionProvider()->getPrimaryDatabase( 'virtual-communityrequests' );
	}

	/** @inheritDoc */
	public function execute() {
		$this->authority = new UltimateAuthority(
			User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] )
		);

		$this->initServices();

		if ( $this->hasOption( 'wishes' ) ) {
			$this->nukeWishes = true;
			$this->nukeFocusAreas = false;
			$this->store = $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
			$this->nukeEntities();
			if ( !$this->hasOption( 'legacy' ) ) {
				$this->truncateTables();
			}
		}
		if ( $this->hasOption( 'focus-areas' ) ) {
			$this->nukeWishes = false;
			$this->nukeFocusAreas = true;
			$this->store = $this->getServiceContainer()->get( 'CommunityRequests.FocusAreaStore' );
			$this->nukeEntities();
			if ( !$this->hasOption( 'legacy' ) ) {
				$this->truncateTables();
			}
		}
		if ( !$this->nukeWishes && !$this->nukeFocusAreas ) {
			$this->showHelp();
			$this->fatalError( 'You must specify either --wishes or --focus-areas option.' );
		}

		$this->output( "Nuke wishlist operation completed successfully.\n" );
	}

	private function nukeEntities(): void {
		$this->output( "Deleting all {$this->store->entityType()} pages and related data...\n" );
		$count = $this->deletePages( NS_MAIN );
		$this->output( "$count {$this->store->entityType()} page(s) and their related data have been deleted.\n" );
		$this->deleteTranslationNsPages();
	}

	private function truncateTables(): void {
		$this->beginTransactionRound( __METHOD__ );
		$tables = [
			AbstractWishlistStore::tableName(),
			AbstractWishlistStore::translationsTableName(),
		];

		if ( $this->nukeWishes ) {
			$tables[] = AbstractWishlistStore::tagsTableName();
		}
		$this->resetIdCounter();

		foreach ( $tables as $table ) {
			if ( $this->hasOption( 'dry-run' ) ) {
				$this->output( "Would truncate table: $table\n" );
				continue;
			}
			$this->dbw->truncateTable( $table, __METHOD__ );
			$this->output( "Truncated table: $table\n" );
		}
		$this->commitTransactionRound( __METHOD__ );
	}

	private function resetIdCounter(): void {
		$type = $this->nukeWishes ? 'wish' : 'focus-area';
		if ( $this->hasOption( 'dry-run' ) ) {
			$this->output( "Would reset ID counters for $type.\n" );
			return;
		}
		$this->dbw->newUpdateQueryBuilder()
			->table( 'communityrequests_counters' )
			->set( [ 'crc_value' => 0 ] )
			->where( [ 'crc_type' => $type === 'wish' ? IdGenerator::TYPE_WISH : IdGenerator::TYPE_FOCUS_AREA ] )
			->caller( __METHOD__ )
			->execute();
		$this->output( "Reset ID counters for $type.\n" );
	}

	/**
	 * Delete all pages in the Translation namespace related to the given entity type.
	 */
	private function deleteTranslationNsPages(): void {
		if ( !$this->getServiceContainer()->getExtensionRegistry()->isLoaded( 'Translate' ) ) {
			return;
		}
		$this->output( "Deleting translation namespace pages...\n" );
		$count = $this->deletePages( NS_TRANSLATIONS );
		$this->output( "$count {$this->store->entityType()} translation pages deleted.\n" );
	}

	private function deletePages( int $nsId ): int {
		$rows = $this->getPages( $nsId );

		$count = 0;
		foreach ( $rows as $row ) {
			$page = PageIdentityValue::localIdentity( (int)$row->page_id, (int)$row->page_namespace, $row->page_title );

			if ( $this->config->isWishOrFocusAreaIndexPage( $page ) ) {
				// Skip index pages and any translation subpages.
				continue;
			}

			$entity = $this->store->get( $page );

			if ( $this->hasOption( 'dry-run' ) ) {
				$this->output( "Would delete page: {$page->__toString()}\n" );
			} else {
				$this->output( "Deleting page: {$page->__toString()}\n" );

				$deletePage = $this->deletePageFactory->newDeletePage( $page, $this->authority );
				$status = $deletePage->deleteIfAllowed( 'CommunityRequests nukeWishlist maintenance script' );
				if ( !$status->isOK() ) {
					$this->fatalError(
						"Failed to delete page {$page->__toString()}: " .
						wfMessage( $status->getMessages()[0] )->text()
					);
				}

				if ( $entity ) {
					$this->store->delete( $entity->getPage()->getId(), $entity->getBaseLang() );
				} elseif ( $this->config->isWishOrFocusAreaPage( $page ) ) {
					$this->output(
						"No entity found for page {$page->__toString()}, skipping related data deletion.\n"
					);
				}
			}

			$count++;
			if ( $count % $this->getBatchSize() === 0 ) {
				$this->output( "Deleted $count pages so far...\n" );
				$this->commitTransactionRound( __METHOD__ );
			}
		}

		return $count;
	}

	/**
	 * Find pages in the main namespace matching the given prefix, but not
	 * matching another prefix
	 */
	private function getPages( int $nsId ): IResultWrapper {
		$prefix = $this->titleParser->parseTitle( $this->nukeWishes
			? $this->config->getWishPagePrefix()
			: $this->config->getFocusAreaPagePrefix()
		)->getDBkey();
		$legacyPath = $this->nukeWishes ? 'Community_Wishlist/Wishes/' : 'Community_Wishlist/Focus_areas/';
		$excludePrefix = $this->titleParser->parseTitle( $legacyPath )->getDBkey();
		$dbr = $this->getDB( DB_REPLICA );
		$wherePrefix = "page_title REGEXP '" . $prefix . "[[:digit:]]+'";
		$whereExcludePrefix = $dbr->expr(
			'page_title',
			IExpression::NOT_LIKE,
			new LikeValue(
				$excludePrefix,
				$dbr->anyString()
			)
		);
		if ( $this->getOption( 'legacy' ) ) {
			$wherePrefix = $dbr->expr(
				'page_title',
				IExpression::LIKE,
				new LikeValue(
					$excludePrefix,
					$dbr->anyString()
				)
			);
			$whereExcludePrefix = "page_title NOT REGEXP '" . $prefix . "[[:digit:]]+'";
		}
		return $dbr->newSelectQueryBuilder()
			->select( [ 'page_id', 'page_namespace', 'page_title' ] )
			->from( 'page' )
			->where( [
				'page_namespace' => $nsId,
				$wherePrefix,
				$whereExcludePrefix,
			] )
			// Roughly order by creation timestamp so that wish IDs will be in
			// ascending order of creation
			->orderBy( 'page_id' )
			->caller( __METHOD__ )
			->fetchResultSet();
	}
}

// @codeCoverageIgnoreStart
$maintClass = NukeWishlist::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
