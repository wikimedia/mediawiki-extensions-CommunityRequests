<?php

namespace MediaWiki\Extension\CommunityRequests\IdGenerator;

use RuntimeException;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\RawSQLValue;

/**
 * Unique Id generator implemented using an SQL table and an UPSERT query.
 * The table needs to have the fields crc_value and crc_type.
 *
 * The UPSERT approach was created in https://phabricator.wikimedia.org/T194299
 * as it was discovered that the old SqlIdGenerator could cause problems.
 *
 * LAST_INSERT_ID from MySQL is used in this class, which means that this IdGenerator
 * can only be used with MySQL.
 * This class depends on the upsert implementation within the RDBMS library for
 * different DB backends.
 *
 * Based on MediaWiki\Extensions\Wikibase\Repo\Store\Sql (GPL-2.0-or-later)
 */
class UpsertSqlIdGenerator implements IdGenerator {

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
	 * @param int $type
	 *
	 * @throws RuntimeException
	 * @return int
	 */
	private function generateNewId( IDatabase $database, int $type ): int {
		$database->startAtomic( __METHOD__ );

		$this->upsertId( $database, $type );
		$id = $database->insertId();

		$database->endAtomic( __METHOD__ );

		// If the upsert successfully inserts, we won't have an auto increment ID,
		// instead it will be the 1 set in the query.
		if ( !is_int( $id ) || $id === 0 ) {
			$id = 1;
		}

		return $id;
	}

	/**
	 * @param IDatabase $database
	 * @param int $type
	 */
	private function upsertId( IDatabase $database, int $type ): void {
		$database->newInsertQueryBuilder()
			->insertInto( 'communityrequests_counters' )
			->row( [
				'crc_type' => $type,
				'crc_value' => 1,
			] )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( 'crc_type' )
			->set( [ 'crc_value' => new RawSQLValue( 'LAST_INSERT_ID(crc_value + 1)' ) ] )
			->caller( __METHOD__ )
			->execute();
	}

}
