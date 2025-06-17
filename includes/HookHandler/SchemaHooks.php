<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use ImportStreamSource;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use RuntimeException;
use WikiImporterFactory;

/**
 * LoadExtensionSchemaUpdates hook handler.
 * This hook handler must not have any service dependencies.
 */
class SchemaHooks implements LoadExtensionSchemaUpdatesHook {

	public const UPDATER_ROW_WISH_TEMPLATE = 'create communityrequests-wish-template';

	public function __construct(
		private WikiImporterFactory $importerFactory
	) {
	}

	/**
	 * @note The hook doesn't allow injecting services
	 * @codeCoverageIgnore
	 * @return self
	 */
	public static function newFromGlobalState(): self {
		return new self(
			MediaWikiServices::getInstance()->getWikiImporterFactory()
		);
	}

	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$sqlDir = __DIR__ . '/../../sql';
		$engine = $updater->getDB()->getType();

		// For developer convenience from when redesigned the schema.
		// TODO: Remove after some time.
		$updater->dropExtensionTable( 'community_requests' );
		$updater->dropExtensionTable( 'community_requests_projects' );
		$updater->dropExtensionTable( 'community_requests_translations' );
		$updater->dropExtensionTable( 'community_requests_focus_areas' );
		$updater->dropExtensionTable( 'community_requests_focus_area_translations' );
		$updater->dropExtensionTable( 'community_requests_projects' );
		$updater->dropExtensionTable( 'community_requests_phab_tasks' );

		$updater->addExtensionTable( 'communityrequests_wishes',
			"$sqlDir/$engine/tables-generated.sql" );
		$updater->addExtensionTable( 'communityrequests_focus_areas',
			"$sqlDir/$engine/tables-generated.sql" );
		$updater->addExtensionTable( 'communityrequests_wishes_translations',
			"$sqlDir/$engine/tables-generated.sql" );
		$updater->addExtensionTable( 'communityrequests_focus_areas_translations',
			"$sqlDir/$engine/tables-generated.sql" );
		$updater->addExtensionTable( 'communityrequests_projects',
			"$sqlDir/$engine/tables-generated.sql" );
		$updater->addExtensionTable( 'communityrequests_phab_tasks',
			"$sqlDir/$engine/tables-generated.sql" );
		$updater->addExtensionTable( 'communityrequests_counters',
			"$sqlDir/$engine/tables-generated.sql" );

		$updater->addExtensionUpdate( [ [ $this, 'addWishTemplate' ] ] );
	}

	/**
	 * Updater callback to add the wish template to the wiki after schema updates.
	 * This does NOT used the $wgCommunityRequestsWishTemplate since (a) extension
	 * configuration is not available at this point, and (b) the XML file itself
	 * hardcodes the template name.
	 *
	 * @param DatabaseUpdater $updater
	 * @return bool Returns true on success, false on failure.
	 */
	public function addWishTemplate( DatabaseUpdater $updater ): bool {
		$templateTitle = Title::newFromText( 'Template:Community Wishlist/Wish' );
		if ( $templateTitle->exists() || $updater->updateRowExists( self::UPDATER_ROW_WISH_TEMPLATE ) ) {
			return false;
		}

		$xmlFilePath = __DIR__ . '/../../templates/CommunityRequestsWishTemplate.xml';
		$source = ImportStreamSource::newFromFile( $xmlFilePath );
		if ( !$source->isOK() ) {
			throw new RuntimeException( 'Failed to create ImportStreamSource from file: ' . $xmlFilePath );
		}

		$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
		$importer = $this->importerFactory->getWikiImporter(
			$source->getValue(),
			new UltimateAuthority( $user )
		);
		$importer->setUsernamePrefix( 'm', true );

		$importer->doImport();
		$updater->insertUpdateRow( self::UPDATER_ROW_WISH_TEMPLATE );

		return true;
	}
}
