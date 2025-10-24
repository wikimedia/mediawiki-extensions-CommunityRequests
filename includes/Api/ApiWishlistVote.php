<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Api;

use MediaWiki\Api\ApiMain;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\Extension\CommunityRequests\Vote\Vote;
use MediaWiki\Extension\CommunityRequests\Vote\VoteStore;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiWishlistVote extends ApiWishlistEditBase {

	public function __construct(
		ApiMain $main,
		string $name,
		protected readonly WikiPageFactory $wikiPageFactory,
		protected readonly VoteStore $store,
		protected readonly WishStore $wishStore,
		protected FocusAreaStore $focusAreaStore,
		WishlistConfig $config
	) {
		parent::__construct( $main, $name, $config );
	}

	/** @inheritDoc */
	public function execute() {
		parent::execute();

		// Require the user to be logged in.
		if ( !$this->getUser()->isNamed() ) {
			$this->dieWithError( 'apierror-wishlist-vote-login-required', 'notloggedin' );
		}

		$entityPageRef = $this->config->getEntityPageRefFromWikitextVal( $this->params['entity'] );
		if ( !$entityPageRef ) {
			$this->dieWithError( 'apierror-wishlist-entity-invalid', 'invalidentity' );
		}
		$entityTitle = Title::newFromPageReference( $entityPageRef );

		$isWish = $this->config->isWishPage( $entityPageRef );
		$isFocusArea = $this->config->isFocusAreaPage( $entityPageRef );
		if ( !$isWish && !$isFocusArea ) {
			$this->dieWithError( 'apierror-wishlist-entity-invalid', 'invalidentity' );
		}

		$entity = $isWish ?
			$this->wishStore->get( $entityTitle ) :
			$this->focusAreaStore->get( $entityTitle );
		if ( !$entity ) {
			$this->dieWithError( 'apierror-wishlist-entity-invalid', 'notfound' );
		}

		$newVote = null;
		if ( $this->params[Vote::PARAM_ACTION] === 'add' ) {
			// Add vote
			$existingVote = $this->store->getForUser( $entity, $this->getUser() );
			$newVote = new Vote( $entity, $this->getUser(), $this->params[Vote::PARAM_COMMENT] ?? '' );
			// Validate we can parse the wikitext back to the same vote.
			$this->doParserValidation( $newVote, $entity );
			[ $wikitext, $baseRevId ] = $this->store->getWikitextWithVoteAdded( $newVote );
			$summary = $existingVote ? 'update' : 'add';
			$sessionValue = $existingVote ?
				CommunityRequestsHooks::SESSION_VALUE_VOTE_UPDATED :
				CommunityRequestsHooks::SESSION_VALUE_VOTE_ADDED;
		} else {
			// Remove vote
			[ $wikitext, $baseRevId ] = $this->store->getWikitextWithVoteRemoved( $entity, $this->getUser() );
			$summary = 'remove';
			$sessionValue = CommunityRequestsHooks::SESSION_VALUE_VOTE_REMOVED;
		}

		// Messages used here include:
		// - communityrequests-vote-add-summary
		// - communityrequests-vote-update-summary
		// - communityrequests-vote-remove-summary
		$summary = $this->msg( "communityrequests-vote-$summary-summary" )->text();
		if ( $this->params[Vote::PARAM_COMMENT] ?? '' ) {
			$summary .= $this->msg( 'colon-separator' )->text() . $this->params[Vote::PARAM_COMMENT];
		}

		// Skip permission checks in CommunityRequestsHooks::onGetUserPermissionsErrorsExpensive()
		CommunityRequestsHooks::$allowManualEditing = true;

		// Save the updated votes page.
		$saveStatus = $this->saveInternal(
			$entityTitle->getPrefixedDBkey() . $this->config->getVotesPageSuffix(),
			$wikitext,
			$summary,
			$this->params['token'],
			$baseRevId
		);

		if ( $saveStatus->isOK() === false ) {
			CommunityRequestsHooks::$allowManualEditing = false;
			$this->dieWithError( $saveStatus->getMessages()[0] );
		}

		$resultData = $saveStatus->getValue()->getResultData()['edit'];
		$resultData[Vote::PARAM_ENTITY] = $this->params[Vote::PARAM_ENTITY];
		$resultData[Vote::PARAM_ACTION] = $this->params[Vote::PARAM_ACTION];
		if ( $newVote ) {
			$resultData += $newVote->toArray( $this->config );
		} else {
			$resultData['removed'] = $this->getUser()->getName();
		}
		$this->getResult()->addValue( null, $this->getModuleName(), $resultData );

		// Set session variable so the edit is tagged in CommunityRequestsHooks::onRecentChange_save(),
		// and a post-edit notice is shown by the ext.communityrequests.voting module.
		$this->getRequest()->getSession()->set( CommunityRequestsHooks::SESSION_KEY, $sessionValue );

		// Purge the cache of the entity page so the vote count updates.
		$this->wikiPageFactory->newFromTitle( $entityTitle )->doPurge();
	}

	private function doParserValidation( Vote $vote, AbstractWishlistEntity $entity ): void {
		$data = (array)$this->store->getDataFromWikitext( $vote->toWikitext() );
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

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			Vote::PARAM_ENTITY => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			Vote::PARAM_COMMENT => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			Vote::PARAM_ACTION => [
				ParamValidator::PARAM_TYPE => [ 'add', 'remove' ],
				ParamValidator::PARAM_DEFAULT => 'add',
			],
		];
	}
}
