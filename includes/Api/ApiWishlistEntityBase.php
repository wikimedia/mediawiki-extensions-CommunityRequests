<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use StatusValue;
use Throwable;

/**
 * Common logic between ApiWishEdit and ApiFocusAreaEdit.
 * These are internal APIs for editing wishlist entities.
 */
abstract class ApiWishlistEntityBase extends ApiBase {

	protected Title $title;
	protected array $params;

	public function __construct(
		ApiMain $main,
		string $name,
		protected readonly WishlistConfig $config,
		protected readonly AbstractWishlistStore $store,
		protected readonly TitleParser $titleParser
	) {
		parent::__construct( $main, $name );
	}

	/** @inheritDoc */
	public function execute() {
		if ( !$this->config->isEnabled() ) {
			$this->dieWithError( 'communityrequests-disabled' );
		}

		$this->params = $this->extractRequestParams();

		// We use a dummy title for validations to avoid prematurely generating a new ID.
		$dummyEntity = $this->getEntity(
			Title::newFromText( $this->store->getPagePrefix() . '99999999' ),
			$this->params
		);

		// Confirm we can parse and then re-create the same wikitext.
		$wikitext = $dummyEntity->toWikitext( $this->config );
		$validationsPassed = false;
		try {
			$validateEntity = $this->getEntity(
				$dummyEntity->getPage(),
				(array)$this->store->getDataFromWikitextContent( $wikitext ),
			);
			if ( $wikitext->getText() === $validateEntity->toWikitext( $this->config )->getText() ) {
				$validationsPassed = true;
			}
		} catch ( Throwable ) {
			// Ignore and fail validations.
		}
		if ( !$validationsPassed ) {
			$this->dieWithError( 'apierror-wishlist-entity-parse' );
		}

		// Validations passed; Now we can safely generate a new ID.
		if ( isset( $this->params[static::entityParam()] ) ) {
			$title = Title::newFromText(
				$this->store->getPagePrefix() .
				$this->store->getIdFromInput( $this->params[static::entityParam()] )
			);
		} else {
			$id = $this->store->getNewId();
			$title = Title::newFromText( $this->store->getPagePrefix() . $id );
		}
		$entity = $this->getEntity( $title, $this->params );

		$saveStatus = $this->save(
			$entity,
			$this->params['token'],
			$this->params[AbstractWishlistEntity::PARAM_BASE_REV_ID] ?? null
		);

		if ( $saveStatus->isOK() === false ) {
			$this->dieWithError( $saveStatus->getMessages()[0] );
		}

		$resultData = $saveStatus->getValue()->getResultData()['edit'];
		// API adds the 'title' key to the result data, but we want to use static::entityParam().
		$resultData[static::entityParam()] = $resultData['title'];
		unset( $resultData['title'] );
		// 'newtimestamp' should be 'updated'.
		if ( isset( $resultData['newtimestamp'] ) ) {
			$resultData[AbstractWishlistEntity::PARAM_UPDATED] = $resultData['newtimestamp'];
			unset( $resultData['newtimestamp'] );
		}
		$ret = $resultData + $entity->toArray( $this->config );
		$this->getResult()->addValue( null, $this->getModuleName(), $ret );
	}

	/**
	 * Save a wishlist item to the wiki through ApiEditPage.
	 *
	 * @param AbstractWishlistEntity $entity
	 * @param string $token
	 * @param int|null $baseRevId
	 * @param array $tags
	 * @return StatusValue
	 */
	protected function save(
		AbstractWishlistEntity $entity,
		string $token,
		?int $baseRevId = null,
		array $tags = []
	): StatusValue {
		$apiParams = [
			'action' => 'edit',
			'title' => Title::newFromPageIdentity( $entity->getPage() )->getPrefixedDBkey(),
			'text' => $entity->toWikitext( $this->config )->getText(),
			'summary' => $this->getEditSummary( $entity ),
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

	/**
	 * The API parameter name for the entity. Either 'wish' or 'focusarea'.
	 *
	 * @return string
	 */
	abstract protected static function entityParam(): string;

	/**
	 * Create a new entity built using AbstractWishlistStore::newFromWikitextParams()
	 * with the provided $params.
	 *
	 * The arguments are necessary so we can first run validations with a dummy title
	 * before generating a new ID or using the real title. The $identity and $params will
	 * either be dummy data or the actual title and $this->params.
	 *
	 * @param PageIdentity $identity
	 * @param array $params
	 * @return AbstractWishlistEntity
	 */
	abstract protected function getEntity( PageIdentity $identity, array $params ): AbstractWishlistEntity;

	/**
	 * Get the edit summary for a wishlist item.
	 *
	 * @param AbstractWishlistEntity $entity
	 * @return string
	 */
	abstract public function getEditSummary( AbstractWishlistEntity $entity ): string;

	/**
	 * The edit summary to use when publishing a wishlist item.
	 *
	 * @return string
	 */
	abstract protected function editSummaryPublish(): string;

	/**
	 * The edit summary to use when saving a wishlist item.
	 *
	 * @return string
	 */
	abstract protected function editSummarySave(): string;

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
}
