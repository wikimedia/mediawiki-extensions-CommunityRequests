<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Content\Transform\ContentTransformer;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageParser;
use MediaWiki\Page\PageIdentity;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\Title\TitleParser;
use MediaWiki\User\UserFactory;
use Psr\Log\LoggerInterface;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\StringDef;

class ApiWishEdit extends ApiWishlistEntityBase {

	public function __construct(
		ApiMain $main,
		string $name,
		WishlistConfig $config,
		LoggerInterface $logger,
		AbstractWishlistStore $store,
		TitleParser $titleParser,
		ContentTransformer $transformer,
		protected readonly UserFactory $userFactory,
		?TranslatablePageParser $translatablePageParser = null,
	) {
		parent::__construct(
			$main, $name, $config, $logger, $store, $titleParser, $transformer, $translatablePageParser
		);
	}

	/** @inheritDoc */
	protected static function entityParam(): string {
		return 'wish';
	}

	/** @inheritDoc */
	protected function getEntity( PageIdentity $identity, array $params ): Wish {
		return Wish::newFromWikitextParams(
			$identity,
			$params[Wish::PARAM_BASE_LANG] ?? '',
			$this->store->normalizeArrayValues( $params, WishStore::ARRAY_DELIMITER_WIKITEXT ),
			$this->config,
			$this->userFactory->newFromName( $params[Wish::PARAM_PROPOSER] ),
		);
	}

	/** @inheritDoc */
	protected function getEditSummaryFields( /** @var Wish $entity */ AbstractWishlistEntity $entity ): array {
		'@phan-var Wish $entity';
		return array_merge( parent::getEditSummaryFields( $entity ), [
			Wish::PARAM_TYPE => fn ( string $type ) => $this->msg(
				$this->config->getWishTypeLabelFromWikitextVal( $type ) . '-label'
			)->inContentLanguage()->text(),
			Wish::PARAM_FOCUS_AREA => function ( string $focusArea ) {
				if ( !$focusArea ) {
					return $this->msg( 'communityrequests-focus-area-unassigned' )->inContentLanguage()->text();
				}
				$faPage = $this->config->getEntityPageRefFromWikitextVal( $focusArea );
				return '[[' . $faPage->getDBkey() . '|' . $focusArea . ']]';
			},
			Wish::PARAM_TAGS => function ( array $tags ) {
				return array_map(
					fn ( string $tagWikitextVal ) => $this->msg(
						(string)$this->config->getTagLabelFromWikitextVal( $tagWikitextVal )
					)->inContentLanguage()->text(),
					$tags
				);
			},
			Wish::PARAM_AUDIENCE => null,
			Wish::PARAM_PHAB_TASKS => function ( array $tasks ) {
				return array_map(
					static fn ( string $taskId ) => "[[phab:$taskId|$taskId]]",
					$tasks
				);
			},
			Wish::PARAM_PROPOSER => null,
		] );
	}

	/** @inheritDoc */
	protected function editSummaryPublish(): string {
		$summary = parent::editSummaryPublish();
		// If there are Phabricator tasks, append them to the edit summary.
		if ( isset( $this->params[Wish::PARAM_PHAB_TASKS] ) && count( $this->params[Wish::PARAM_PHAB_TASKS] ) ) {
			return $summary . ' ' .
				$this->msg( 'parentheses-start' )->inContentLanguage()->text() .
				$this->getLanguage()->commaList( array_map(
					static fn ( string $taskId ) => "[[phab:$taskId|$taskId]]",
					$this->params[Wish::PARAM_PHAB_TASKS]
				) ) .
				$this->msg( 'parentheses-end' )->inContentLanguage()->text();
		}
		return $summary;
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
