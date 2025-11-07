<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Api;

use MediaWiki\Api\ApiMain;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\Extension\CommunityRequests\Vote\Vote;
use MediaWiki\Extension\CommunityRequests\Vote\VoteStore;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use Psr\Log\LoggerInterface;
use Wikimedia\ParamValidator\ParamValidator;

class ApiWishlistVote extends ApiWishlistEditBase {

	public function __construct(
		ApiMain $main,
		string $name,
		WishlistConfig $config,
		LoggerInterface $logger,
		WikiPageFactory $wikiPageFactory,
		protected readonly VoteStore $store,
		protected readonly WishStore $wishStore,
		protected FocusAreaStore $focusAreaStore,
	) {
		parent::__construct( $main, $name, $config, $logger, $wikiPageFactory );
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

		$saveStatus = $this->saveVote(
			$entity,
			$this->store,
			$this->params[Vote::PARAM_ACTION] === 'add',
			$this->params[Vote::PARAM_COMMENT] ?? ''
		);
		if ( $saveStatus->isOK() === false ) {
			CommunityRequestsHooks::$allowManualEditing = false;
			$this->dieWithError( $saveStatus->getMessages()[0] );
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
