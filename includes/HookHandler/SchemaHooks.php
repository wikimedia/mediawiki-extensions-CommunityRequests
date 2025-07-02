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

	public const CACHE_VERSION = 2;
	public const UPDATER_ROW_WISH_TEMPLATE = 'create communityrequests-wish-template' . self::CACHE_VERSION;
	public const UPDATER_ROW_FOCUS_AREA_TEMPLATE = 'create communityrequests-focus-area-template' . self::CACHE_VERSION;

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
		$updater->addExtensionUpdate( [ [ $this, 'addFocusAreaTemplate' ] ] );
	}

	/**
	 * Updater callback to add the wish template to the wiki after schema updates.
	 * This does NOT use the $wgCommunityRequestsWishTemplate since (a) extension
	 * configuration is not available at this point, and (b) the XML file itself
	 * hardcodes the template name.
	 *
	 * @param DatabaseUpdater $updater
	 * @return bool Returns true on success, false on failure.
	 */
	public function addWishTemplate( DatabaseUpdater $updater ): bool {
		return $this->addTemplate(
			$updater,
			'Wish',
			self::UPDATER_ROW_WISH_TEMPLATE
		);
	}

	/**
	 * Updater callback to add the focus area template to the wiki after schema updates.
	 * Similar to the wish template, this does not go by configuration.
	 * Sysadmins who want to use a different template should move the page after its created,
	 * and update the configuration accordingly.
	 *
	 * @param DatabaseUpdater $updater
	 * @return bool Returns true on success, false on failure.
	 */
	public function addFocusAreaTemplate( DatabaseUpdater $updater ): bool {
		return $this->addTemplate(
			$updater,
			'FocusArea',
			self::UPDATER_ROW_FOCUS_AREA_TEMPLATE
		);
	}

	private function addTemplate( DatabaseUpdater $updater, string $entityName, string $updaterRow ): bool {
		$templateTitle = Title::newFromText( "Template:Community Wishlist/$entityName" );
		if ( $templateTitle->exists() || $updater->updateRowExists( $updaterRow ) ) {
			return false;
		}

		$xmlFilePath = __DIR__ . "/../../templates/CommunityRequests{$entityName}Template.xml";
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
		$updater->insertUpdateRow( $updaterRow );

		return true;
	}
}
