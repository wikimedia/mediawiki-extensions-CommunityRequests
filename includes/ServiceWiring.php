<?php

use MediaWiki\Extension\CommunityRequests\Store\IdGenerator;
use MediaWiki\Extension\CommunityRequests\Store\SqlIdGenerator;
use MediaWiki\Extension\CommunityRequests\Store\UpsertSqlIdGenerator;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [
	'CommunityRequests.IdGenerator' => static function ( MediaWikiServices $services ): IdGenerator {
		$dbType = $services->getMainConfig()->get( MainConfigNames::DBtype );
		$connectionProvider = $services->getConnectionProvider();
		if ( $dbType === 'mysql' ) {
			return new UpsertSqlIdGenerator( $connectionProvider );
		}
		return new SqlIdGenerator( $connectionProvider );
	},
];
