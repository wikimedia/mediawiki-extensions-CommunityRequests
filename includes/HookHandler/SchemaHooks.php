<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * LoadExtensionSchemaUpdates hook handler.
 * This hook handler must not have any service dependencies.
 */
class SchemaHooks implements LoadExtensionSchemaUpdatesHook {

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

		// communityrequests_counters is the one table that's remained unmodified since
		// the initial implementation, so we use it as the indicator of a first-time install.
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-communityrequests', 'addTable', 'communityrequests_counters',
			"$sqlDir/$engine/tables-generated.sql", true
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-communityrequests', 'addTable', 'communityrequests_tags',
			"$sqlDir/$engine/patch-communityrequests_projects_tags.sql", true
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-communityrequests', 'addField', 'communityrequests_tags', 'crtg_tag',
			"$sqlDir/$engine/patch-communityrequests_wishes_tags-key-renames.sql", true
		] );

		// Drop cwt_other_project which now lives as a 'wikitext field'.
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-communityrequests', 'dropField', 'communityrequests_wishes_translations', 'cwt_other_project',
			"$sqlDir/$engine/patch-communityrequests_wishes_translations-drop-cwt_other_project.sql", true
		] );

		// Drop communityrequests_phab_tasks which now lives as a 'wikitext field'.
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-communityrequests', 'dropTable', 'communityrequests_phab_tasks',
			"$sqlDir/$engine/patch-communityrequests_phab_tasks-drop-table.sql", true
		] );

		// Merge communityrequests_wishes and _focus_areas into communityrequests_entities.
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-communityrequests', 'addTable', 'communityrequests_entities',
			"$sqlDir/$engine/patch-communityrequests_entities.sql", true
		] );
		// Merge the two translation tables into communityrequests_translations.
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-communityrequests', 'addTable', 'communityrequests_translations',
			"$sqlDir/$engine/patch-communityrequests_translations.sql", true
		] );
	}
}
