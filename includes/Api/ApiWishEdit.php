<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Page\PageIdentity;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\Title\TitleParser;
use MediaWiki\User\UserFactory;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\StringDef;

class ApiWishEdit extends ApiWishlistEntityBase {

	public function __construct(
		ApiMain $main,
		string $name,
		WishlistConfig $config,
		AbstractWishlistStore $store,
		TitleParser $titleParser,
		protected readonly UserFactory $userFactory,
	) {
		parent::__construct( $main, $name, $config, $store, $titleParser );
	}

	/** @inheritDoc */
	protected static function entityParam(): string {
		return 'wish';
	}

	/** @inheritDoc */
	protected function getEntity( PageIdentity $identity, array $params ): Wish {
		return Wish::newFromWikitextParams(
			$identity,
			$params[Wish::PARAM_BASE_LANG],
			$this->store->normalizeArrayValues( $params, WishStore::ARRAY_DELIMITER_WIKITEXT ),
			$this->config,
			$this->userFactory->newFromName( $params[Wish::PARAM_PROPOSER] ),
		);
	}

	/** @inheritDoc */
	public function getEditSummary( AbstractWishlistEntity $entity ): string {
		'@phan-var Wish $entity';
		$summary = trim( $this->params['wish'] ?? '' ) ? $this->editSummarySave() : $this->editSummaryPublish();

		// If there are Phabricator tasks, add them to the edit summary.
		if ( count( $entity->getPhabTasks() ) > 0 ) {
			$taskLinks = array_map(
				static fn ( int $taskId ) => "[[phab:T{$taskId}|T{$taskId}]]",
				$entity->getPhabTasks()
			);
			$summary .= ' ' .
				$this->msg( 'parentheses-start' )->text() .
				$this->getLanguage()->commaList( $taskLinks ) .
				$this->msg( 'parentheses-end' )->text();
		}
		return $summary;
	}

	/** @inheritDoc */
	protected function editSummaryPublish(): string {
		return $this->msg( 'communityrequests-publish-wish-summary', $this->params[Wish::PARAM_TITLE] )
			->inContentLanguage()
			->text();
	}

	/** @inheritDoc */
	protected function editSummarySave(): string {
		return $this->msg( 'communityrequests-save-wish-summary', $this->params[Wish::PARAM_TITLE] )
			->inContentLanguage()
			->text();
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		// NOTE: Keys should match the Wish::PARAM_* constants where possible.
		return [
			static::entityParam() => [ ParamValidator::PARAM_TYPE => 'string' ],
			Wish::PARAM_STATUS => [
				ParamValidator::PARAM_TYPE => array_keys( $this->config->getStatuses() ),
				ParamValidator::PARAM_REQUIRED => true,
			],
			Wish::PARAM_FOCUS_AREA => [ ParamValidator::PARAM_TYPE => 'string' ],
			Wish::PARAM_TITLE => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				StringDef::PARAM_MAX_BYTES => WishStore::TITLE_MAX_BYTES,
			],
			Wish::PARAM_DESCRIPTION => [
				ParamValidator::PARAM_TYPE => 'text',
				ParamValidator::PARAM_REQUIRED => true,
			],
			Wish::PARAM_TYPE => [
				ParamValidator::PARAM_TYPE => array_keys( $this->config->getWishTypes() ),
				ParamValidator::PARAM_REQUIRED => true,
			],
			Wish::PARAM_TAGS => [
				ParamValidator::PARAM_TYPE => array_values( array_map(
					fn ( array $tag ) => $this->config->getTagWikitextValFromId( $tag['id'] ),
					$this->config->getNavigationTags()
				) ),
				ParamValidator::PARAM_ISMULTI => true,
			],
			Wish::PARAM_AUDIENCE => [
				ParamValidator::PARAM_TYPE => 'string',
				StringDef::PARAM_MAX_CHARS => WishStore::AUDIENCE_MAX_CHARS,
			],
			Wish::PARAM_PHAB_TASKS => [
				// TODO: maybe make our own TypeDef for Phab tasks?
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_ISMULTI => true,
			],
			Wish::PARAM_PROPOSER => [
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name' ],
				ParamValidator::PARAM_REQUIRED => true,
			],
			Wish::PARAM_CREATED => [
				ParamValidator::PARAM_TYPE => 'timestamp',
				ParamValidator::PARAM_REQUIRED => true,
			],
			Wish::PARAM_BASE_LANG => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			Wish::PARAM_BASE_REV_ID => [
				ParamValidator::PARAM_TYPE => 'integer',
				ApiBase::PARAM_HELP_MSG => 'apihelp-edit-param-baserevid',
			],
		];
	}
}
