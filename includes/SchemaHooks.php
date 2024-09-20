<?php

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * LoadExtensionSchemaUpdates hook handler.
 * This hook handler must not have any service dependencies.
 */
class SchemaHooks implements LoadExtensionSchemaUpdatesHook {

	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$sqlDir = __DIR__ . '/../sql';
		$engine = $updater->getDB()->getType();
		$updater->addExtensionTable( 'community_requests',
			"$sqlDir/$engine/community_requests.sql" );
		$updater->addExtensionTable( 'community_requests_projects',
			"$sqlDir/$engine/community_requests_projects.sql" );
		$updater->addExtensionTable( 'community_requests_phab_tasks',
			"$sqlDir/$engine/community_requests_phab_tasks.sql" );
		$updater->addExtensionTable( 'community_requests_focus_areas',
			"$sqlDir/$engine/community_requests_focus_areas.sql" );
		$updater->addExtensionTable( 'community_requests_votes',
			"$sqlDir/$engine/community_requests_votes.sql" );
	}
}
