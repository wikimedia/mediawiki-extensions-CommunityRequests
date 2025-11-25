<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Page\PageIdentity;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\StringDef;

class ApiFocusAreaEdit extends ApiWishlistEntityBase {

	/** @inheritDoc */
	public function execute() {
		$this->checkUserRightsAny( 'manage-wishlist' );
		parent::execute();
	}

	/** @inheritDoc */
	protected static function entityParam(): string {
		return 'focusarea';
	}

	/** @inheritDoc */
	protected function getEntity( PageIdentity $identity, array $params ): FocusArea {
		return FocusArea::newFromWikitextParams(
			$identity,
			$params[FocusArea::PARAM_BASE_LANG] ?? '',
			$this->store->normalizeArrayValues( $params, FocusAreaStore::ARRAY_DELIMITER_WIKITEXT ),
			$this->config
		);
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		// NOTE: Keys should match the FocusArea::PARAM_* constants where possible.
		return [
			static::entityParam() => [ ParamValidator::PARAM_TYPE => 'string' ],
			FocusArea::PARAM_STATUS => [
				ParamValidator::PARAM_TYPE => array_keys( $this->config->getStatuses() ),
				ParamValidator::PARAM_REQUIRED => true,
			],
			FocusArea::PARAM_TITLE => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				StringDef::PARAM_MAX_BYTES => FocusAreaStore::TITLE_MAX_BYTES,
			],
			FocusArea::PARAM_DESCRIPTION => [
				ParamValidator::PARAM_TYPE => 'text',
				ParamValidator::PARAM_REQUIRED => true,
			],
			FocusArea::PARAM_SHORT_DESCRIPTION => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			FocusArea::PARAM_OWNERS => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			FocusArea::PARAM_VOLUNTEERS => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			FocusArea::PARAM_CREATED => [
				ParamValidator::PARAM_TYPE => 'timestamp',
				ParamValidator::PARAM_REQUIRED => true,
			],
			FocusArea::PARAM_BASE_LANG => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			FocusArea::PARAM_BASE_REV_ID => [
				ParamValidator::PARAM_TYPE => 'integer',
				ApiBase::PARAM_HELP_MSG => 'apihelp-edit-param-baserevid',
			],
		];
	}
}
