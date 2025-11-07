<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\Extension\CommunityRequests\Vote\Vote;
use MediaWiki\Extension\CommunityRequests\Vote\VoteStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Status\Status;
use Psr\Log\LoggerInterface;
use StatusValue;

/**
 * Common logic for internal CommunityRequests edit APIs.
 */
class ApiWishlistEditBase extends ApiBase {

	protected array $params;

	public function __construct(
		ApiMain $main,
		string $name,
		protected readonly WishlistConfig $config,
		protected readonly LoggerInterface $logger,
		protected readonly WikiPageFactory $wikiPageFactory,
	) {
		parent::__construct( $main, $name );
	}

	/** @inheritDoc */
	public function execute() {
		if ( !$this->config->isEnabled() ) {
			$this->dieWithError( 'communityrequests-disabled' );
		}

		$this->params = $this->extractRequestParams();
	}

	/**
	 * Make an action=edit API request.
	 *
	 * @param string $title
	 * @param string $text
	 * @param string $summary
	 * @param string $token
	 * @param int|null $baseRevId
	 * @param array $tags
	 * @return StatusValue
	 */
	protected function saveInternal(
		string $title,
		string $text,
		string $summary,
		string $token,
		?int $baseRevId = null,
		array $tags = []
	): StatusValue {
		$apiParams = [
			'action' => 'edit',
			'title' => $title,
			'text' => $text,
			'summary' => $summary,
			'token' => $token,
			'baserevid' => $baseRevId,
			'tags' => implode( '|', $tags ),
			'errorformat' => 'html',
			'notminor' => true,
		];

		$context = new DerivativeContext( $this->getContext() );
		$context->setRequest( new DerivativeRequest( $this->getRequest(), $apiParams ) );
		$api = new ApiMain( $context, true );

		try {
			$api->execute();
		} catch ( ApiUsageException $e ) {
			return $e->getStatusValue();
		}
		return Status::newGood( $api->getResult() );
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'csrf';
	}

	/** @inheritDoc */
	public function isInternal() {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/**
	 * Save a vote for an entity.
	 *
	 * @param AbstractWishlistEntity $entity The wish or focus area to operate on.
	 * @param VoteStore $voteStore
	 * @param bool $add Whether adding (true) or removing (false) a vote.
	 * @param string $comment The vote's comment.
	 *
	 * @return StatusValue
	 */
	protected function saveVote(
		AbstractWishlistEntity $entity,
		VoteStore $voteStore,
		bool $add = true,
		string $comment = ''
	): StatusValue {
		$newVote = null;
		if ( $add ) {
			// Add vote
			$existingVote = $voteStore->getForUser( $entity, $this->getUser() );
			$newVote = new Vote( $entity, $this->getUser(), $comment );
			// Validate we can parse the wikitext back to the same vote.
			$this->doVoteParserValidation( $voteStore, $newVote, $entity );
			[ $wikitext, $baseRevId ] = $voteStore->getWikitextWithVoteAdded( $newVote );
			$summary = $existingVote ? 'update' : 'add';
			$sessionValue = $existingVote ?
				CommunityRequestsHooks::SESSION_VALUE_VOTE_UPDATED :
				CommunityRequestsHooks::SESSION_VALUE_VOTE_ADDED;
		} else {
			// Remove vote
			[ $wikitext, $baseRevId ] = $voteStore->getWikitextWithVoteRemoved( $entity, $this->getUser() );
			$summary = 'remove';
			$sessionValue = CommunityRequestsHooks::SESSION_VALUE_VOTE_REMOVED;
		}

		// Messages used here include:
		// - communityrequests-vote-add-summary
		// - communityrequests-vote-update-summary
		// - communityrequests-vote-remove-summary
		$summary = $this->msg( "communityrequests-vote-$summary-summary" )->text();
		if ( $comment ) {
			$summary .= $this->msg( 'colon-separator' )->text() . $comment;
		}

		// Skip permission checks in CommunityRequestsHooks::onGetUserPermissionsErrorsExpensive()
		CommunityRequestsHooks::$allowManualEditing = true;

		// Save the updated votes page.
		$saveStatus = $this->saveInternal(
			$entity->getPage()->getDBkey() . $this->config->getVotesPageSuffix(),
			$wikitext,
			$summary,
			$this->params['token'],
			$baseRevId
		);
		if ( !$saveStatus->isOK() ) {
			return $saveStatus;
		}

		// Purge the cache of the entity page so the vote count updates.
		$this->wikiPageFactory->newFromTitle( $entity->getPage() )->doPurge();

		// Set session variable so the edit is tagged in CommunityRequestsHooks::onRecentChange_save(),
		// and a post-edit notice is shown by the ext.communityrequests.voting module.
		$this->getRequest()->getSession()->set( CommunityRequestsHooks::SESSION_KEY, $sessionValue );
		$resultData = $saveStatus->getValue()->getResultData()['edit'];
		$resultData[Vote::PARAM_ENTITY] = basename( $entity->getPage()->getDBkey() );
		$resultData[Vote::PARAM_ACTION] = $add ? 'add' : 'remove';
		if ( $newVote ) {
			$resultData += $newVote->toArray( $this->config );
		} else {
			$resultData['removed'] = $this->getUser()->getName();
		}
		$this->getResult()->addValue( null, 'wishlistvote', $resultData );

		return $saveStatus;
	}

	private function doVoteParserValidation( VoteStore $voteStore, Vote $vote, AbstractWishlistEntity $entity ): void {
		$data = (array)$voteStore->getDataFromWikitext( $vote->toWikitext() );
		$validationVote = new Vote(
			$entity,
			$this->getUser(),
			$data[Vote::PARAM_COMMENT] ?? '',
			$vote->getTimestamp(),
		);
		if ( $validationVote->toWikitext()->getText() !== $vote->toWikitext()->getText() ) {
			$this->dieWithError( 'apierror-wishlist-vote-parse' );
		}
	}
}
