<?php

namespace MediaWiki\Extension\CommunityRequests\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use StatusValue;

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
		$title = $this->getWishlistEntityTitle( $this->params );

		if ( !$title ) {
			$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $this->params[ 'wish' ] ) ] );
		} elseif ( !$title->canExist() ) {
			$this->dieWithError( 'apierror-pagecannotexist' );
		}

		$this->title = $title;
		$this->getErrorFormatter()->setContextTitle( $this->title );

		$this->executeWishlistEntity();
	}

	/**
	 * Constructs the AbstractWishlistEntity, calls ::save(), and adds to the ApiResult.
	 */
	abstract protected function executeWishlistEntity(): void;

	/**
	 * Save a wishlist item to the wiki through ApiEditPage.
	 *
	 * @param WikitextContent $content
	 * @param string $summary
	 * @param string $token
	 * @param int|null $baseRevId
	 * @param array $tags
	 * @return StatusValue
	 */
	protected function save(
		WikitextContent $content,
		string $summary,
		string $token,
		?int $baseRevId = null,
		array $tags = []
	): StatusValue {
		$apiParams = [
			'action' => 'edit',
			'title' => $this->title->getPrefixedDBkey(),
			'text' => $content->getText(),
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

		// FIXME: make use of EditFilterMergedContent hook to impose our own edit checks
		//   (Status will show up in SpecialFormPage) Such as a missing proposer or invalid creation date.
		try {
			$api->execute();
		} catch ( ApiUsageException $e ) {
			return $e->getStatusValue();
		}
		return Status::newGood( $api->getResult() );
	}

	/**
	 * Get the title of the wishlist entity based on the provided parameters.
	 *
	 * @param array $params
	 * @return ?Title
	 */
	abstract protected function getWishlistEntityTitle( array $params ): ?Title;

	/**
	 * Get the edit summary for a wishlist item.
	 *
	 * @param AbstractWishlistEntity $entity
	 * @param array $params
	 * @return string
	 */
	abstract public function getEditSummary( AbstractWishlistEntity $entity, array $params ): string;

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
