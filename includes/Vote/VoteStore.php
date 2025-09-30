<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Vote;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\ArgumentExtractor;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Page\PageReference;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use RuntimeException;

class VoteStore {

	public function __construct(
		protected UserFactory $userFactory,
		protected RevisionStore $revisionStore,
		protected ParserFactory $parserFactory,
		protected readonly WishlistConfig $config
	) {
	}

	/**
	 * Get all vote data from the votes subpage of the given entity.
	 *
	 * @param AbstractWishlistEntity $entity
	 * @return Vote[]
	 * @throws RuntimeException If the content type is not WikitextContent
	 */
	public function getAll( AbstractWishlistEntity $entity ): array {
		$revRecord = $this->revisionStore->getRevisionByTitle(
			Title::castFromPageReference( $this->votesPageRef( $entity ) )->toPageIdentity()
		);
		if ( !$revRecord ) {
			return [];
		}
		$content = $revRecord->getMainContentRaw();
		if ( !$content instanceof WikitextContent ) {
			throw new RuntimeException( 'Invalid content type for Votes subpage' );
		}
		return array_filter( array_map(
			function ( string $row ) use ( $entity ) {
				$data = $this->getDataFromWikitext( $row );
				if ( !$data ) {
					return null;
				}
				$user = $this->userFactory->newFromName( $data[Vote::PARAM_USERNAME] );
				if ( !$user || !$user->isRegistered() ) {
					return null;
				}
				return new Vote(
					$entity,
					$user,
					$data[Vote::PARAM_COMMENT],
					$data[Vote::PARAM_TIMESTAMP]
				);
			},
			explode( "\n", $content->getText() )
		) );
	}

	/**
	 * Get the vote for a specific user on the given entity, or null if none is found.
	 *
	 * @param AbstractWishlistEntity $entity
	 * @param UserIdentity $user
	 * @return ?Vote
	 * @throws RuntimeException If the content type is not WikitextContent
	 */
	public function getForUser( AbstractWishlistEntity $entity, UserIdentity $user ): ?Vote {
		$allVotes = $this->getAll( $entity );
		foreach ( $allVotes as $vote ) {
			if ( $vote->getUser()->equals( $user ) ) {
				return $vote;
			}
		}
		return null;
	}

	/**
	 * Save a vote. If a vote by the same user already exists, it will be replaced.
	 *
	 * @param Vote $vote
	 * @return string The new, raw wikitext for the votes subpage.
	 */
	public function getWikitextWithVoteAdded( Vote $vote ): string {
		$allVotes = $this->getAll( $vote->getEntity() );
		$found = false;
		foreach ( $allVotes as $i => $existingVote ) {
			if ( $existingVote->getUser()->equals( $vote->getUser() ) ) {
				$allVotes[$i] = $vote;
				$found = true;
				break;
			}
		}
		if ( !$found ) {
			$allVotes[] = $vote;
		}
		$contentText = '';
		foreach ( $allVotes as $v ) {
			$contentText .= $v->toWikitext()->getText();
		}
		return trim( $contentText );
	}

	/**
	 * Get the wikitext for the votes subpage with the vote by the given user removed.
	 * If no such vote exists, the wikitext will be unchanged.
	 *
	 * @param AbstractWishlistEntity $entity
	 * @param UserIdentity $user
	 * @return string The new, raw wikitext for the votes subpage.
	 * @throws RuntimeException If the content type is not WikitextContent
	 */
	public function getWikitextWithVoteRemoved( AbstractWishlistEntity $entity, UserIdentity $user ): string {
		$allVotes = $this->getAll( $entity );
		$allVotes = array_filter(
			$allVotes,
			static fn ( Vote $v ) => !$v->getUser()->equals( $user )
		);
		$contentText = '';
		foreach ( $allVotes as $v ) {
			$contentText .= $v->toWikitext()->getText();
		}
		return trim( $contentText );
	}

	private function votesPageRef( AbstractWishlistEntity $entity ): PageReference {
		return PageReferenceValue::localReference(
			$entity->getPage()->getNamespace(),
			$entity->getPage()->getDBkey() . $this->config->getVotesPageSuffix()
		);
	}

	public function getDataFromWikitext( WikitextContent|string $content ): ?array {
		if ( $content instanceof WikitextContent ) {
			$content = $content->getText();
		}
		return ( new ArgumentExtractor( $this->parserFactory ) )
			->getFuncArgs( 'communityrequests', 'vote', $content );
	}
}
