<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
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
}
