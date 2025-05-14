<?php

declare( strict_types = 1 );

use MediaWiki\Tests\Structure\AbstractSchemaTestBase;

/**
 * @group CommunityRequests
 * @coversNothing
 */
class CommunityRequestsSchemaTest extends AbstractSchemaTestBase {
	protected static function getSchemasDirectory(): string {
		return __DIR__ . '/../../../sql';
	}

	protected static function getSchemaChangesDirectory(): string {
		return __DIR__ . '/../../../sql/changes';
	}

	protected static function getSchemaSQLDirs(): array {
		return [
			'mysql' => __DIR__ . '/../../../sql/mysql/',
			'sqlite' => __DIR__ . '/../../../sql/sqlite',
			'postgres' => __DIR__ . '/../../../sql/postgres',
		];
	}
}
