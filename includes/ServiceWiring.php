<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\Extension\CommunityRequests\IdGenerator\SqlIdGenerator;
use MediaWiki\Extension\CommunityRequests\IdGenerator\UpsertSqlIdGenerator;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;

/** @phpcs-require-sorted-array */
return [
	'CommunityRequests.FocusAreaStore' => static function (
	MediaWikiServices $services ): FocusAreaStore {
		return new FocusAreaStore(
			$services->getConnectionProvider(),
			$services->get( 'CommunityRequests.WishlistConfig' ),
		);
	},
	'CommunityRequests.IdGenerator' => static function ( MediaWikiServices $services ): IdGenerator {
		$dbType = $services->getMainConfig()->get( MainConfigNames::DBtype );
		$connectionProvider = $services->getConnectionProvider();
		if ( $dbType === 'mysql' ) {
			return new UpsertSqlIdGenerator( $connectionProvider );
		}
		return new SqlIdGenerator( $connectionProvider );
	},
	'CommunityRequests.Logger' => static function ( MediaWikiServices $services ): LoggerInterface {
		return LoggerFactory::getInstance( 'communityrequests' );
	},
	'CommunityRequests.WishlistConfig' => static function ( MediaWikiServices $services ): WishlistConfig {
		return new WishlistConfig(
			new ServiceOptions(
				WishlistConfig::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getTitleParser(),
			$services->getTitleFormatter(),
		);
	},
	'CommunityRequests.WishStore' => static function ( MediaWikiServices $services ): WishStore {
		return new WishStore(
			$services->getActorNormalization(),
			$services->getConnectionProvider(),
			$services->getUserFactory(),
			$services->getLanguageFallback(),
			$services->getRevisionStore(),
			$services->getParserFactory(),
			$services->getTitleParser(),
			$services->getTitleFormatter(),
			$services->get( 'CommunityRequests.IdGenerator' ),
			$services->get( 'CommunityRequests.WishlistConfig' ),
		);
	}
];
