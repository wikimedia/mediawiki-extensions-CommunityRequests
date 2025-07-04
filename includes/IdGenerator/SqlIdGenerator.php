<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\IdGenerator;

use RuntimeException;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;

/**
 * Unique Id generator implemented using an SQL table.
 * The table needs to have the fields crc_value and crc_type.
 *
 * Based on MediaWiki\Extensions\Wikibase\Repo\Store\Sql\SqlIdGenerator (GPL-2.0-or-later)
 */
class SqlIdGenerator implements IdGenerator {

	private IConnectionProvider $dbProvider;

	/**
	 * @param IConnectionProvider $dbProvider
	 */
	public function __construct( IConnectionProvider $dbProvider ) {
		$this->dbProvider = $dbProvider;
	}

	/**
	 * @inheritDoc
	 */
	public function getNewId( int $type ): int {
		$database = $this->dbProvider->getPrimaryDatabase();
		return $this->generateNewId( $database, $type );
	}

	/**
	 * Generates and returns a new ID.
	 *
	 * @param IDatabase $database
	 * @param int $type One of the IdGenerator::TYPE_ constants
	 *
	 * @throws RuntimeException
	 * @return int
	 */
	private function generateNewId( IDatabase $database, int $type ): int {
		$database->startAtomic( __METHOD__ );

		$currentId = $database->newSelectQueryBuilder()
			->select( 'crc_value' )
			->from( 'communityrequests_counters' )
			->where( [ 'crc_type' => $type ] )
			->forUpdate()
			->caller( __METHOD__ )->fetchRow();

		if ( is_object( $currentId ) ) {
			$id = $currentId->crc_value + 1;
			$database->newUpdateQueryBuilder()
				->update( 'communityrequests_counters' )
				->set( [ 'crc_value' => $id ] )
				->where( [ 'crc_type' => $type ] )
				->caller( __METHOD__ )->execute();
		} else {
			$id = 1;

			$database->newInsertQueryBuilder()
				->insertInto( 'communityrequests_counters' )
				->row( [
					'crc_type' => $type,
					'crc_value' => $id,
				] )
				->caller( __METHOD__ )->execute();
		}

		$database->endAtomic( __METHOD__ );
		return $id;
	}

}
