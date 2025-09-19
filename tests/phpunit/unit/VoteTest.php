<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\Vote\Vote;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\User\UserIdentity;
use MockTitleTrait;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\Vote\Vote
 */
class VoteTest extends AbstractWishlistEntityTest {
	use MockTitleTrait;

	public function testGetters(): void {
		$entity = Wish::newFromWikitextParams(
			$this->makeMockTitle( 'Community Wishlist/W1' ),
			'en',
			[
				Wish::PARAM_TITLE => 'Test Wish',
				Wish::PARAM_TYPE => 'bug',
				Wish::PARAM_PROPOSER => 'TestUser',
				Wish::PARAM_BASE_LANG => 'en',
				Wish::PARAM_CREATED => '2025-01-01T00:00:00Z',
			],
			$this->config
		);
		$vote = new Vote(
			$entity,
			$this->getUser( 'TestUser' ),
			'This is a comment',
			'2025-01-01T12:00:00Z'
		);
		$this->assertSame( $entity, $vote->getEntity() );
		$this->assertSame( 'TestUser', $vote->getUser()->getName() );
		$this->assertSame( 'This is a comment', $vote->getComment() );
		$this->assertSame( '2025-01-01T12:00:00Z', $vote->getTimestamp() );
	}

	public function testToArray(): void {
		$entity = Wish::newFromWikitextParams(
			$this->makeMockTitle( 'Community Wishlist/W1' ),
			'en',
			[
				Wish::PARAM_TITLE => 'Test Wish',
				Wish::PARAM_TYPE => 'bug',
				Wish::PARAM_PROPOSER => 'TestUser',
				Wish::PARAM_BASE_LANG => 'en',
				Wish::PARAM_CREATED => '2025-01-01T00:00:00Z',
			],
			$this->config
		);
		$vote = new Vote(
			$entity,
			$this->getUser( 'TestUser' ),
			'This is a comment',
			'2025-01-01T12:00:00Z'
		);
		$expected = [
			'entity' => 'W1',
			'username' => 'TestUser',
			'comment' => 'This is a comment',
			'timestamp' => '2025-01-01T12:00:00Z',
		];
		$this->assertSame( $expected, $vote->toArray( $this->config ) );
	}

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
