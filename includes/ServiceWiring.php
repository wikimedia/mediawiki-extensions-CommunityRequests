<?php
declare( strict_types = 1 );

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommunityRequests\ChangesProcessorFactory;
use MediaWiki\Extension\CommunityRequests\EntityFactory;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\Extension\CommunityRequests\IdGenerator\SqlIdGenerator;
use MediaWiki\Extension\CommunityRequests\IdGenerator\UpsertSqlIdGenerator;
use MediaWiki\Extension\CommunityRequests\Vote\VoteStore;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;

/** @phpcs-require-sorted-array */
return [
	'CommunityRequests.ChangesProcessorFactory' => static function (
		MediaWikiServices $services
	): ChangesProcessorFactory {
		return new ChangesProcessorFactory(
			$services->get( 'CommunityRequests.WishlistConfig' ),
			$services->get( 'CommunityRequests.WishStore' ),
			$services->get( 'CommunityRequests.FocusAreaStore' ),
			$services->get( 'ContentTransformer' ),
			$services->has( 'Translate:TranslatablePageParser' ) ?
				$services->get( 'Translate:TranslatablePageParser' ) :
				null,
			$services->get( 'CommunityRequests.Logger' ),
		);
	},
	'CommunityRequests.EntityFactory' => static function ( MediaWikiServices $services ): EntityFactory {
		return new EntityFactory(
			$services->get( 'CommunityRequests.WishlistConfig' ),
			$services->getUserFactory()
		);
	},
	'CommunityRequests.FocusAreaStore' => static function ( MediaWikiServices $services ): FocusAreaStore {
		return new FocusAreaStore(
			$services->getConnectionProvider(),
			$services->getLanguageFallback(),
			$services->getRevisionStore(),
			$services->getParserFactory(),
			$services->getTitleParser(),
			$services->getTitleFormatter(),
			$services->getPageStore(),
			$services->get( 'CommunityRequests.IdGenerator' ),
			$services->get( 'CommunityRequests.WishlistConfig' ),
			$services->get( 'CommunityRequests.Logger' ),
			$services->has( 'Translate:TranslatablePageParser' ) ?
				$services->get( 'Translate:TranslatablePageParser' ) :
				null,
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
	'CommunityRequests.Logger' => static function (): LoggerInterface {
		return LoggerFactory::getInstance( 'communityrequests' );
	},
	'CommunityRequests.VoteStore' => static function ( MediaWikiServices $services ): VoteStore {
		return new VoteStore(
			$services->getUserFactory(),
			$services->getRevisionStore(),
			$services->getParserFactory(),
			$services->get( 'CommunityRequests.WishlistConfig' )
		);
	},
	'CommunityRequests.WishlistConfig' => static function ( MediaWikiServices $services ): WishlistConfig {
		return new WishlistConfig(
			new ServiceOptions(
				WishlistConfig::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getTitleParser(),
			$services->getTitleFormatter(),
			$services->getLanguageNameUtils(),
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
			$services->getPageStore(),
			$services->get( 'CommunityRequests.IdGenerator' ),
			$services->get( 'CommunityRequests.WishlistConfig' ),
			$services->get( 'CommunityRequests.Logger' ),
			$services->has( 'Translate:TranslatablePageParser' ) ?
				$services->get( 'Translate:TranslatablePageParser' ) :
				null,
		);
	}
];
