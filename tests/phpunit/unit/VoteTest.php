<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\Vote\Vote;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\Vote\Vote
 */
class VoteTest extends MediaWikiUnitTestCase {

	public function testToWikitext() {
		$vote = new Vote(
			$this->createMock( AbstractWishlistEntity::class ),
			$this->getUser( 'TestUser' ),
			'This is a comment',
			'2025-01-01T12:00:00Z'
		);
		$expected = '{{#CommunityRequests:vote|username=TestUser|' .
			"comment=This is a comment|timestamp=2025-01-01T12:00:00Z}}\n";
		$this->assertSame( $expected, $vote->toWikitext()->getText() );
	}

	private function getUser( string $name ): UserIdentity {
		$mockUser = $this->createMock( UserIdentity::class );
		$mockUser->method( 'getName' )->willReturn( $name );
		return $mockUser;
	}
}
