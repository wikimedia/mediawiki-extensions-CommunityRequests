<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\Extension\CommunityRequests\Vote\VoteStore;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\Api\ApiWishlistVote
 * @covers \MediaWiki\Extension\CommunityRequests\Api\ApiWishlistEditBase
 */
class ApiWishlistVoteTest extends ApiTestCase {
	use WishlistTestTrait;
	use MockAuthorityTrait;

	protected readonly VoteStore $voteStore;

	protected function getVoteStore(): VoteStore {
		if ( !isset( $this->voteStore ) ) {
			$this->voteStore = $this->getServiceContainer()->get( 'CommunityRequests.VoteStore' );
		}
		return $this->voteStore;
	}

	protected function getStore(): WishStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	public function testAddNewVote(): void {
		$wish = $this->insertTestWish( $this->config->getWishPagePrefix() . '123' );
		[ $ret ] = $this->doApiRequestWithToken( [
			'action' => 'wishlistvote',
			'entity' => 'W123',
			'comment' => 'My comment',
			'voteaction' => 'add',
		] );
		$this->assertTrue( $ret['wishlistvote']['new'] );
		$this->assertGreaterThan( 0, $ret['wishlistvote']['pageid'] );
		$this->assertSame( 'Community Wishlist/W123/Votes', $ret['wishlistvote']['title'] );
		$this->assertSame( 'W123', $ret['wishlistvote']['entity'] );
		$this->assertSame( 'add', $ret['wishlistvote']['voteaction'] );
		$this->assertSame( $this->getTestSysop()->getUser()->getName(), $ret['wishlistvote']['username'] );
		$this->assertSame( 'My comment', $ret['wishlistvote']['comment'] );
		$this->assertNotNull( $this->getVoteStore()->getForUser( $wish, $this->getTestSysop()->getUser() ) );
	}

	public function testUpdateVote(): void {
		$this->insertTestWish( $this->config->getWishPagePrefix() . '123' );
		ConvertibleTimestamp::setFakeTime( '2025-01-01T12:00:00Z', 1 );
		[ $ret ] = $this->doApiRequestWithToken( [
			'action' => 'wishlistvote',
			'entity' => 'W123',
			'comment' => 'My comment',
			'voteaction' => 'add',
		] );
		$this->assertTrue( $ret['wishlistvote']['new'] );
		$firstTimestamp = $ret['wishlistvote']['timestamp'];

		[ $ret ] = $this->doApiRequestWithToken( [
			'action' => 'wishlistvote',
			'entity' => 'W123',
			'comment' => 'My updated comment',
			'voteaction' => 'add',
		] );
		$this->assertArrayNotHasKey( 'new', $ret['wishlistvote'] );
		$this->assertSame( 'My updated comment', $ret['wishlistvote']['comment'] );
		$this->assertGreaterThan( $firstTimestamp, $ret['wishlistvote']['timestamp'] );
	}

	public function testRemoveVote(): void {
		$wish = $this->insertTestWish( $this->config->getWishPagePrefix() . '123' );
		[ $ret ] = $this->doApiRequestWithToken( [
			'action' => 'wishlistvote',
			'entity' => 'W123',
			'comment' => 'My comment',
			'voteaction' => 'add',
		] );
		$this->assertTrue( $ret['wishlistvote']['new'] );

		[ $ret ] = $this->doApiRequestWithToken( [
			'action' => 'wishlistvote',
			'entity' => 'W123',
			'voteaction' => 'remove',
		] );
		$this->assertSame( 'remove', $ret['wishlistvote']['voteaction'] );
		$this->assertSame( $this->getTestSysop()->getUser()->getName(), $ret['wishlistvote']['removed'] );
		$this->assertNull( $this->getVoteStore()->getForUser( $wish, $this->getTestSysop()->getUser() ) );
	}

	public function testAddVoteWithParsingFailure(): void {
		$this->insertTestWish( $this->config->getWishPagePrefix() . '123' );
		$this->expectApiErrorCode( 'wishlist-vote-parse' );
		$this->doApiRequestWithToken( [
			'action' => 'wishlistvote',
			'entity' => 'W123',
			'comment' => "My comment}}",
			'voteaction' => 'add',
		] );
	}

	public function testAddVoteLoggedOut(): void {
		$this->insertTestWish( $this->config->getWishPagePrefix() . '123' );
		$this->expectApiErrorCode( 'notloggedin' );
		$this->doApiRequestWithToken( [
			'action' => 'wishlistvote',
			'entity' => 'W123',
			'comment' => 'My comment',
			'voteaction' => 'add',
		], null, $this->mockAnonNullAuthority() );
	}

	public function testTagEdits(): void {
		$this->insertTestWish( $this->config->getWishPagePrefix() . '123' );
		[ ,, $sessionData ] = $this->doApiRequestWithToken( [
			'action' => 'wishlistvote',
			'entity' => 'W123',
			'comment' => 'My comment',
			'voteaction' => 'add',
		] );
		$this->assertSame(
			CommunityRequestsHooks::SESSION_VALUE_VOTE_ADDED,
			$sessionData[CommunityRequestsHooks::SESSION_KEY] ?? null
		);
	}
}
