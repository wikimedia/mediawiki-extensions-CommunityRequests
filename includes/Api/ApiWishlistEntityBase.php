<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Api;

use MediaWiki\Api\ApiMain;
use MediaWiki\Content\Transform\ContentTransformer;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageParser;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use StatusValue;
use Throwable;

/**
 * Common logic between ApiWishEdit and ApiFocusAreaEdit.
 * These are internal APIs for editing wishlist entities.
 */
abstract class ApiWishlistEntityBase extends ApiWishlistEditBase {

	protected Title $title;

	public function __construct(
		ApiMain $main,
		string $name,
		WishlistConfig $config,
		protected readonly AbstractWishlistStore $store,
		protected readonly TitleParser $titleParser,
		protected readonly ContentTransformer $transformer,
		protected readonly ?TranslatablePageParser $translatablePageParser = null,
	) {
		parent::__construct( $main, $name, $config );
	}

	/** @inheritDoc */
	public function execute() {
		parent::execute();

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

		$oldEntity = null;

		// Validations passed; Now we can safely generate a new ID.
		if ( isset( $this->params[static::entityParam()] ) ) {
			$title = Title::newFromText(
				$this->store->getPagePrefix() .
				$this->store->getIdFromInput( $this->params[static::entityParam()] )
			);
			$oldEntity = $this->store->get( $title, null, AbstractWishlistStore::FETCH_WIKITEXT_TRANSLATED );
		} else {
			$id = $this->store->getNewId();
			$title = Title::newFromText( $this->store->getPagePrefix() . $id );
		}
		$entity = $this->getEntity( $title, $this->params );

		$saveStatus = $this->save(
			$entity,
			$this->params['token'],
			$this->params[AbstractWishlistEntity::PARAM_BASE_REV_ID] ?? null,
			$oldEntity
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
	 * @param ?int $baseRevId
	 * @param ?AbstractWishlistEntity $oldEntity
	 * @return StatusValue
	 */
	protected function save(
		AbstractWishlistEntity $entity,
		string $token,
		?int $baseRevId = null,
		?AbstractWishlistEntity $oldEntity = null,
	): StatusValue {
		return $this->saveInternal(
			Title::newFromPageIdentity( $entity->getPage() )->getPrefixedDBkey(),
			$entity->toWikitext( $this->config )->getText(),
			$this->getEditSummary( $entity, $oldEntity ),
			$token,
			$baseRevId
		);
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
	 * Generate an automatic edit summary based on the fields that changed.
	 *
	 * @param AbstractWishlistEntity $entity
	 * @param ?AbstractWishlistEntity $oldEntity
	 * @return string
	 */
	public function getEditSummary( AbstractWishlistEntity $entity, ?AbstractWishlistEntity $oldEntity ): string {
		if ( !$oldEntity ) {
			return $this->editSummaryPublish();
		}

		$oldValues = $this->getValuesForEditSummary( $oldEntity );
		$newValues = $this->getValuesForEditSummary( $entity );

		$changesList = [];
		foreach ( $oldValues as $field => $oldValue ) {
			$newValue = $newValues[$field] ?? null;
			if ( $oldValue !== $newValue ) {
				$changesList = array_merge( $changesList,
					$this->getMessagesForFieldChange( $field, $newValues[$field], $oldValue )
				);
			}
		}

		return $this->getLanguage()->semicolonList( $changesList );
	}

	/**
	 * The fields to include in the edit summary when an entity is edited.
	 *
	 * Keys are AbstractWishlistEntity::PARAM_* constants.
	 * Values can be null (no processing) or a callback that takes the
	 * field value and returns a processed value for the summary.
	 *
	 * Overrides should merge parent::getEditSummaryFields().
	 *
	 * @param AbstractWishlistEntity $entity
	 * @return array
	 */
	protected function getEditSummaryFields( AbstractWishlistEntity $entity ): array {
		return [
			AbstractWishlistEntity::PARAM_TITLE => null,
			AbstractWishlistEntity::PARAM_DESCRIPTION => null,
			AbstractWishlistEntity::PARAM_STATUS => function ( string $status ) {
				return $this->msg(
					(string)$this->config->getStatusLabelFromWikitextVal( $this->store->entityType(), $status )
				)->inContentLanguage()->text();
			},
		];
	}

	private function getMessagesForFieldChange(
		string $field,
		string|array $value,
		// phpcs:ignore MediaWiki.Usage.NullableType.ExplicitNullableTypes -- false positive
		string|array|null $oldValue = null
	): array {
		$isArrayField = is_array( $value ) || is_array( $oldValue );
		$isWikitextField = in_array( $field, $this->store->getWikitextFields(), true );

		if ( $isWikitextField && !$isArrayField ) {
			// We don't want to include the value of wikitext fields as they can be very large.
			return [ $this->msg( "communityrequests-entity-summary-$field-updated" )
				->inContentLanguage()
				->text() ];
		}
		$msgKey = "communityrequests-entity-summary-{$field}";
		if ( $oldValue ) {
			if ( $isArrayField ) {
				$added = array_diff( $value, $oldValue );
				$removed = array_diff( $oldValue, $value );
				$parts = [];
				if ( $added ) {
					$parts[] = $this->msg(
						"$msgKey-added",
						$this->getLanguage()->commaList( $added ),
						count( $added )
					)->inContentLanguage()->text();
				}
				if ( $removed ) {
					$parts[] = $this->msg(
						"$msgKey-removed",
						$this->getLanguage()->commaList( $removed ),
						count( $removed )
					)->inContentLanguage()->text();
				}
				return $parts;
			} else {
				return [ $this->msg( "$msgKey-changed", $oldValue, $value )->inContentLanguage()->text() ];
			}
		}
		return [];
	}

	private function getValuesForEditSummary( AbstractWishlistEntity $entity ): array {
		$entityData = $entity->toArray( $this->config );
		$ret = [];
		foreach ( $this->getEditSummaryFields( $entity ) as $field => $callback ) {
			$value = $callback ?
				$callback( $entityData[$field] ?? null ) :
				$entityData[$field] ?? null;
			if ( in_array( $field, $this->store->getExtTranslateFields() ) &&
				$this->translatablePageParser?->containsMarkup( $value )
			) {
				$value = $this->translatablePageParser->cleanupTags( $value );
			}
			if ( in_array( $field, $this->store->getWikitextFields() ) && !is_array( $value ) ) {
				/** @var WikitextContent $content */
				$content = $this->transformer->preSaveTransform(
					new WikitextContent( $value ),
					$entity->getPage(),
					$this->getUser(),
					ParserOptions::newFromUserAndLang( $this->getUser(), $this->getLanguage() )
				);
				'@phan-var WikitextContent $content';
				$value = $content->getText();
			}

			$ret[$field] = $value;
		}
		return $ret;
	}

	/**
	 * The edit summary to use when publishing a new wishlist entity.
	 *
	 * @return string
	 */
	protected function editSummaryPublish(): string {
		return $this->msg( "communityrequests-publish-{$this->store->entityType()}-summary",
				$this->params[AbstractWishlistEntity::PARAM_TITLE]
			)
			->inContentLanguage()
			->text();
	}
}
