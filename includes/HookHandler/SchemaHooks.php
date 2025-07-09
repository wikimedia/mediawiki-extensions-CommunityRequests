<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * LoadExtensionSchemaUpdates hook handler.
 * This hook handler must not have any service dependencies.
 */
class SchemaHooks implements LoadExtensionSchemaUpdatesHook {

	public const CACHE_VERSION = 2;

	/**
	 * @note The hook doesn't allow injecting services
	 * @codeCoverageIgnore
	 * @return self
	 */
	public static function newFromGlobalState(): self {
		return new self();
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
	}
}
