<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Title\Title;

/**
 * @group CommunityRequests
 * @group Database
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\Vote\VoteRenderer
 */
class VoteRendererTest extends CommunityRequestsIntegrationTestCase {

	protected function getStore(): AbstractWishlistStore {
		return $this->store;
	}

	/**
	 * @covers ::render
	 */
	public function testCountVotesOnWishPage(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );

		$wishTitleStr = $this->config->getWishPagePrefix() . '123';
		$wish = $this->insertTestWish( $wishTitleStr, 'en', '3333-01-23T00:00:00Z' );
		$this->assertSame( 0, $wish->getVoteCount() );

		$this->insertVotes( $wishTitleStr, 3 );

		$wish = $this->store->get( Title::newFromText( $wishTitleStr ), 'en' );
		$this->assertSame( 3, $wish->getVoteCount() );
	}

	/**
	 * @covers ::render
	 */
	public function testCountVotesOnFocusAreaPage(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.FocusAreaStore' );

		$focusAreaTitleStr = $this->config->getFocusAreaPagePrefix() . '123';
		$focusArea = $this->insertTestFocusArea( $focusAreaTitleStr, 'en', '3333-01-23T00:00:00Z' );
		$this->assertSame( 0, $focusArea->getVoteCount() );

		$this->insertVotes( $focusAreaTitleStr, 3 );

		$focusArea = $this->store->get( Title::newFromText( $focusAreaTitleStr ), 'en' );
		$this->assertSame( 3, $focusArea->getVoteCount() );
	}

	/**
	 * @covers ::render
	 */
	public function testUpdateVotesCount(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );

		$wishTitleStr = $this->config->getWishPagePrefix() . '123';
		$wish = $this->insertTestWish( $wishTitleStr, 'en', '3333-01-23T00:00:00Z' );
		$this->assertSame( 0, $wish->getVoteCount() );

		$this->store->save(
			Wish::newFromWikitextParams( $wish->getPage(), 'en', [
					'votecount' => 5,
					'proposer' => $wish->getProposer(),
					'created' => $wish->getCreated(),
				],
				$this->config
			)
		);

		$this->insertVotes( $wishTitleStr, 1 );

		$wish = $this->store->get( Title::newFromText( $wishTitleStr ), 'en' );
		$this->assertSame( 1, $wish->getVoteCount() );
	}

	/**
	 * @covers ::render
	 */
	public function testWishUpdateShouldNotWipeCount(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );

		$wishTitleStr = $this->config->getWishPagePrefix() . '123';
		$wish = $this->insertTestWish( $wishTitleStr, 'en', '3333-01-23T00:00:00Z' );
		$this->assertSame( 0, $wish->getVoteCount() );

		$this->insertVotes( $wishTitleStr, 5 );

		$this->store->save(
			Wish::newFromWikitextParams( $wish->getPage(), 'en', [
					'title' => 'Updated title',
					'proposer' => $wish->getProposer(),
					'created' => $wish->getCreated(),
				],
				$this->config
			)
		);

		$wish = $this->store->get( Title::newFromText( $wishTitleStr ), 'en' );
		$this->assertSame( 5, $wish->getVoteCount() );
	}

	/**
	 * @covers ::render
	 */
	public function testFocusAreaUpdateShouldNotWipeCount(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.FocusAreaStore' );

		$focusAreaTitleStr = $this->config->getFocusAreaPagePrefix() . '123';
		$focusArea = $this->insertTestFocusArea( $focusAreaTitleStr, 'en', '3333-01-23T00:00:00Z' );
		$this->assertSame( 0, $focusArea->getVoteCount() );

		$this->insertVotes( $focusAreaTitleStr, 5 );

		$this->store->save(
			FocusArea::newFromWikitextParams( $focusArea->getPage(), 'en', [
					'title' => 'Updated title',
					'created' => $focusArea->getCreated(),
				],
				$this->config
			)
		);

		$focusArea = $this->store->get( Title::newFromText( $focusAreaTitleStr ), 'en' );
		$this->assertSame( 5, $focusArea->getVoteCount() );
	}

	/**
	 * @param string $entityPageTitle
	 * @param int $numVotes
	 * @return array
	 */
	protected function insertVotes( string $entityPageTitle, int $numVotes ): array {
		$wikitext = 'No votes yet.';
		for ( $i = 1; $i <= $numVotes; $i++ ) {
			$wikitext .= <<<END
{{#CommunityRequests: vote
|username = TestUser$i
|timestamp = 3333-01-23T00:00:00Z
|comment = This is a [[test]] }}
END;
		}

		$votesTitle = Title::newFromText( $entityPageTitle . $this->config->getVotesPageSuffix() );
		return $this->insertPage(
			$votesTitle,
			$wikitext,
			NS_MAIN
		);
	}
}
