<?php

use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\Extension\CommunityRequests\IdGenerator\SqlIdGenerator;
use MediaWiki\Extension\CommunityRequests\IdGenerator\UpsertSqlIdGenerator;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
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
	'CommunityRequests.WishStore' => static function ( MediaWikiServices $services ): WishStore {
		return new WishStore(
			$services->getActorNormalization(),
			$services->getConnectionProvider(),
			$services->getUserFactory(),
			$services->getLanguageFallback(),
			$services->getTitleParser(),
			$services->getTitleFormatter(),
			$services->getMainConfig()
		);
	},
];
