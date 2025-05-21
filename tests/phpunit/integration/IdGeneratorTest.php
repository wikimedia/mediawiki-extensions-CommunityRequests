<?php

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;

/**
 * Based on MediaWiki\Extensions\Wikibase\Repo\Tests\Store\IdGeneratorTest (GPL-2.0-or-later)
 *
 * @covers \MediaWiki\Extension\CommunityRequests\IdGenerator\SqlIdGenerator
 * @covers \MediaWiki\Extension\CommunityRequests\IdGenerator\UpsertSqlIdGenerator
 *
 * @group CommunityRequests
 * @group Database
 */
class IdGeneratorTest extends MediaWikiIntegrationTestCase {

	public function testGetNewId(): void {
		$generator = MediaWikiServices::getInstance()->get( 'CommunityRequests.IdGenerator' );
		$clone = clone $generator;

		$id1 = $generator->getNewId( IdGenerator::TYPE_WISH );
		$this->assertSame( 1, $id1 );

		$id2 = $generator->getNewId( IdGenerator::TYPE_WISH );
		$this->assertSame( 2, $id2 );

		$id3 = $generator->getNewId( IdGenerator::TYPE_FOCUS_AREA );
		$this->assertSame( 1, $id3 );

		$id3 = $clone->getNewId( IdGenerator::TYPE_WISH );
		$this->assertSame( 3, $id3 );
	}
}
