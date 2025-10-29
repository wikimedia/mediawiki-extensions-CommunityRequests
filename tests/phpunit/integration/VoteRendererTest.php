<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\Vote\VoteRenderer
 * @covers \MediaWiki\Extension\CommunityRequests\RendererFactory
 */
class VoteRendererTest extends MediaWikiIntegrationTestCase {
	use WishlistTestTrait;

	protected function getStore(): AbstractWishlistStore {
		return $this->store;
	}

	public function testCountVotesOnWishPage(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );

		$wishTitleStr = $this->config->getWishPagePrefix() . '123';
		$wish = $this->insertTestWish( $wishTitleStr );
		$this->assertSame( 0, $wish->getVoteCount() );

		$this->insertVotes( $wishTitleStr, 3 );

		$wish = $this->store->get( Title::newFromText( $wishTitleStr ), 'en' );
		$this->assertSame( 3, $wish->getVoteCount() );
	}

	public function testCountVotesOnFocusAreaPage(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.FocusAreaStore' );

		$focusAreaTitleStr = $this->config->getFocusAreaPagePrefix() . '123';
		$focusArea = $this->insertTestFocusArea( $focusAreaTitleStr );
		$this->assertSame( 0, $focusArea->getVoteCount() );

		$this->insertVotes( $focusAreaTitleStr, 3 );

		$focusArea = $this->store->get( Title::newFromText( $focusAreaTitleStr ), 'en' );
		$this->assertSame( 3, $focusArea->getVoteCount() );
	}

	public function testUpdateVotesCount(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );

		$wishTitleStr = $this->config->getWishPagePrefix() . '123';
		$wish = $this->insertTestWish( $wishTitleStr );
		$this->assertSame( 0, $wish->getVoteCount() );

		$this->store->save(
			Wish::newFromWikitextParams( $wish->getPage(), 'en', [
					Wish::PARAM_TITLE => 'Test wish',
					Wish::PARAM_VOTE_COUNT => 5,
					Wish::PARAM_PROPOSER => $wish->getProposer(),
					Wish::PARAM_CREATED => $wish->getCreated(),
				],
				$this->config
			)
		);

		$this->insertVotes( $wishTitleStr, 1 );

		$wish = $this->store->get( Title::newFromText( $wishTitleStr ), 'en' );
		$this->assertSame( 1, $wish->getVoteCount() );
	}

	public function testWishUpdateShouldNotWipeCount(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );

		$wishTitleStr = $this->config->getWishPagePrefix() . '123';
		$wish = $this->insertTestWish( $wishTitleStr );
		$this->assertSame( 0, $wish->getVoteCount() );

		$this->insertVotes( $wishTitleStr, 5 );

		$this->store->save(
			Wish::newFromWikitextParams( $wish->getPage(), 'en', [
					Wish::PARAM_TITLE => 'Updated title',
					Wish::PARAM_PROPOSER => $wish->getProposer(),
					Wish::PARAM_CREATED => $wish->getCreated(),
				],
				$this->config
			)
		);

		$wish = $this->store->get( Title::newFromText( $wishTitleStr ), 'en' );
		$this->assertSame( 5, $wish->getVoteCount() );
	}

	public function testCountResetToZero(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );

		$wishTitleStr = $this->config->getWishPagePrefix() . '123';
		$wish = $this->insertTestWish( $wishTitleStr );
		$this->assertSame( 0, $wish->getVoteCount() );

		$this->insertVotes( $wishTitleStr, 3 );

		$wish = $this->store->get( Title::newFromText( $wishTitleStr ), 'en' );
		$this->assertSame( 3, $wish->getVoteCount() );

		$this->insertVotes( $wishTitleStr, 0 );

		$wish = $this->store->get( Title::newFromText( $wishTitleStr ), 'en' );
		$this->assertSame( 0, $wish->getVoteCount() );
	}

	public function testFocusAreaUpdateShouldNotWipeCount(): void {
		$this->store = $this->getServiceContainer()->get( 'CommunityRequests.FocusAreaStore' );

		$focusAreaTitleStr = $this->config->getFocusAreaPagePrefix() . '123';
		$focusArea = $this->insertTestFocusArea( $focusAreaTitleStr );
		$this->assertSame( 0, $focusArea->getVoteCount() );

		$this->insertVotes( $focusAreaTitleStr, 5 );

		$this->store->save(
			FocusArea::newFromWikitextParams( $focusArea->getPage(), 'en', [
					FocusArea::PARAM_TITLE => 'Updated title',
					FocusArea::PARAM_CREATED => $focusArea->getCreated(),
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
		$wikitext = '';
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
