<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CommunityRequests\Vote\Vote;
use MediaWiki\Extension\CommunityRequests\Vote\VoteStore;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\MainConfigNames;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\ObjectCache\EmptyBagOStuff;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\Vote\VoteStore
 * @covers \MediaWiki\Extension\CommunityRequests\Vote\Vote
 */
class VoteStoreTest extends MediaWikiIntegrationTestCase {

	use WishlistTestTrait;

	protected VoteStore $voteStore;
	private array $userObjs = [];

	protected function setUp(): void {
		parent::setUp();
		$this->voteStore = $this->getServiceContainer()->get( 'CommunityRequests.VoteStore' );
		$this->config = $this->getServiceContainer()->get( 'CommunityRequests.WishlistConfig' );
		$this->overrideConfigValues( [
			MainConfigNames::NamespacesWithSubpages => [ NS_MAIN => true ],
			MainConfigNames::LanguageCode => 'en',
			MainConfigNames::PageLanguageUseDB => true,
		] );
		$this->setService( 'LocalServerObjectCache', new EmptyBagOStuff() );

		$this->userObjs[] = User::createNew( 'UserA' );
		$this->userObjs[] = User::createNew( 'UserB' );
		$this->userObjs[] = User::createNew( 'UserC' );
	}

	protected function getStore(): WishStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	public function testGetAll(): void {
		$wish = $this->insertTestWishWithVotes();
		$votes = $this->voteStore->getAll( $wish );

		$this->assertCount( 3, $votes );
		$this->assertSame( 'UserA', $votes[0]->getUser()->getName() );
		$this->assertSame( 'First vote!', $votes[0]->getComment() );
		$this->assertSame( '2025-01-01T12:00:00Z', $votes[0]->getTimestamp() );
		$this->assertSame( 'UserB', $votes[1]->getUser()->getName() );
		$this->assertSame( 'Second vote!', $votes[1]->getComment() );
		$this->assertSame( '2025-02-02T12:00:00Z', $votes[1]->getTimestamp() );
		$this->assertSame( 'UserC', $votes[2]->getUser()->getName() );
		$this->assertSame( '', $votes[2]->getComment() );
		$this->assertSame( '2025-03-03T12:00:00Z', $votes[2]->getTimestamp() );
	}

	public function testGetForUser(): void {
		$wish = $this->insertTestWishWithVotes();
		$vote = $this->voteStore->getForUser( $wish, $this->userObjs[1] );
		$this->assertNotNull( $vote );
		$this->assertSame( 'UserB', $vote->getUser()->getName() );
		$this->assertSame( 'Second vote!', $vote->getComment() );
		$this->assertSame( '2025-02-02T12:00:00Z', $vote->getTimestamp() );
	}

	public function testGetWikitextWithVoteAdded(): void {
		$wish = $this->insertTestWishWithVotes();
		$newVote1 = new Vote( $wish, User::createNew( 'UserD' ), 'Another vote!', '2025-04-04T12:00:00Z' );
		$wikitext = $this->voteStore->getWikitextWithVoteAdded( $newVote1 );
		$this->assertStringContainsString(
			'{{#CommunityRequests:vote|username=UserD|comment=Another vote!|timestamp=2025-04-04T12:00:00Z}}',
			$wikitext
		);
		// Adding a vote for a user who already voted should replace their old vote.
		$newVote2 = new Vote( $wish, $this->userObjs[2], 'Modifying my vote', '2025-05-05T12:00:00Z' );
		$wikitext = $this->voteStore->getWikitextWithVoteAdded( $newVote2 );
		$this->assertStringContainsString(
			'{{#CommunityRequests:vote|username=UserC|comment=Modifying my vote|timestamp=2025-05-05T12:00:00Z}}',
			$wikitext
		);
	}

	public function testGetWikitextWithVoteRemoved(): void {
		$wish = $this->insertTestWishWithVotes();
		$wikitext = $this->voteStore->getWikitextWithVoteRemoved( $wish, $this->userObjs[1] );
		$this->assertSame(
			"{{#CommunityRequests:vote|username=UserA|comment=First vote!|timestamp=2025-01-01T12:00:00Z}}\n" .
				'{{#CommunityRequests:vote|username=UserC|comment=|timestamp=2025-03-03T12:00:00Z}}',
			$wikitext
		);
	}

	public function testGetDataFromWikitext(): void {
		$data = $this->voteStore->getDataFromWikitext(
			new WikitextContent(
				"{{#CommunityRequests:vote|username=UserA|comment=First vote!|timestamp=2025-01-01T12:00:00Z}}\n"
			)
		);
		$this->assertSame( [
			Vote::PARAM_USERNAME => 'UserA',
			Vote::PARAM_COMMENT => 'First vote!',
			Vote::PARAM_TIMESTAMP => '2025-01-01T12:00:00Z',
		], $data );
	}

	private function insertTestWishWithVotes(): Wish {
		$wish = $this->insertTestWish( $this->config->getWishPagePrefix() . '123', 'en' );
		$vote1 = new Vote( $wish, $this->userObjs[0], 'First vote!', '2025-01-01T12:00:00Z' );
		$vote2 = new Vote( $wish, $this->userObjs[1], 'Second vote!', '2025-02-02T12:00:00Z' );
		$vote3 = new Vote( $wish, $this->userObjs[2], '', '2025-03-03T12:00:00Z' );

		$this->insertPage(
			$wish->getPage()->getDBkey() . $this->config->getVotesPageSuffix(),
			$vote1->toWikitext()->getText() .
			$vote2->toWikitext()->getText() .
			$vote3->toWikitext()->getText()
		);

		return $wish;
	}
}
