<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use InvalidArgumentException;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageParser;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Page\PageStore;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleParser;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Shared logic for WishStore and FocusAreaStore.
 */
abstract class AbstractWishlistStore {

	public const TITLE_MAX_CHARS = 100;
	public const TITLE_MAX_BYTES = 255;
	public const MAX_FOCUS_AREAS = 1000;
	public const SORT_ASC = SelectQueryBuilder::SORT_ASC;
	public const SORT_DESC = SelectQueryBuilder::SORT_DESC;
	public const FILTER_NONE = [];
	public const FILTER_FOCUS_AREAS = 'focus_area_page_ids';
	public const FOCUS_AREA_UNASSIGNED = 'unassigned';

	public const ARRAY_DELIMITER_WIKITEXT = ',';
	public const ARRAY_DELIMITER_API = '|';
	public const ARRAY_DELIMITER_SPECIAL = null;

	/**
	 * No wikitext fields will be fetched. All data comes from the CommunityRequests database tables.
	 */
	public const FETCH_WIKITEXT_NONE = 0;
	/**
	 * Fetch wikitext fields as-is, including any <translate> tags.
	 * Use this when fetching the content for editing such as in Special:WishlistIntake.
	 */
	public const FETCH_WIKITEXT_RAW = 1;
	/**
	 * Fetch wikitext fields after being processed by Extension:Translate.
	 * Use this when displaying translated content, such as in the APIs.
	 */
	public const FETCH_WIKITEXT_TRANSLATED = 2;

	// Used for the cr_entity_type field in the database.
	public const ENTITY_TYPE_WISH = 0;
	public const ENTITY_TYPE_FOCUS_AREA = 1;

	public function __construct(
		protected IConnectionProvider $dbProvider,
		protected LanguageFallback $languageFallback,
		protected RevisionStore $revisionStore,
		protected ParserFactory $parserFactory,
		protected TitleParser $titleParser,
		protected TitleFormatter $titleFormatter,
		protected PageStore $pageStore,
		protected IdGenerator $idGenerator,
		protected WishlistConfig $config,
		protected LoggerInterface $logger,
		protected ?TranslatablePageParser $translatablePageParser
	) {
	}

	/**
	 * Get the entity type for this store, either "wish" or "focus-area"
	 *
	 * @return string
	 */
	abstract public function entityType(): string;

	/**
	 * Get the numeric ID for the entity type, as stored in the database.
	 *
	 * @return int
	 */
	private function entityTypeId(): int {
		return match ( $this->entityType() ) {
			'wish' => static::ENTITY_TYPE_WISH,
			'focus-area' => static::ENTITY_TYPE_FOCUS_AREA,
			default => throw new RuntimeException( 'Unknown entity type: ' . $this->entityType() ),
		};
	}

	/**
	 * Get the table name for wishlist entities.
	 *
	 * @return string
	 */
	public static function tableName(): string {
		return 'communityrequests_entities';
	}

	/**
	 * Get the field names that should be used in SELECT queries.
	 *
	 * @return string[]
	 */
	public static function fields(): array {
		return [
			static::pageField(),
			static::entityTypeField(),
			static::statusField(),
			static::titleField(),
			static::actorField(),
			static::voteCountField(),
			static::baseLangField(),
			static::translationLangField(),
			static::createdField(),
			static::updatedField(),
		];
	}

	/**
	 * Get the field names for the page field of ::tableName().
	 *
	 * @return string
	 */
	public static function pageField(): string {
		return 'cr_page';
	}

	/**
	 * The field name for the entity type.
	 *
	 * @return string
	 */
	public static function entityTypeField(): string {
		return 'cr_entity_type';
	}

	/**
	 * The field name for the status of the entity.
	 *
	 * @return string
	 */
	public static function statusField(): string {
		return 'cr_status';
	}

	/**
	 * The field name for the title of the wishlist entity.
	 *
	 * @return string
	 */
	public static function titleField(): string {
		return 'crt_title';
	}

	/**
	 * The field name for the actor (user) who created the entity.
	 *
	 * @return string
	 */
	public static function actorField(): string {
		return 'cr_actor';
	}

	/**
	 * The field name for the vote count.
	 *
	 * @return string
	 */
	public static function voteCountField(): string {
		return 'cr_vote_count';
	}

	/**
	 * The field name for the base language.
	 *
	 * @return string
	 */
	public static function baseLangField(): string {
		return 'cr_base_lang';
	}

	/**
	 * The field name for the creation timestamp.
	 *
	 * @return string
	 */
	public static function createdField(): string {
		return 'cr_created';
	}

	/**
	 * The field name for the last updated timestamp.
	 *
	 * @return string
	 */
	public static function updatedField(): string {
		return 'cr_updated';
	}

	/**
	 * Get the table name for the translations of wishlist entities.
	 *
	 * @return string
	 */
	public static function translationsTableName(): string {
		return 'communityrequests_translations';
	}

	/**
	 * The field name in the translations table that is the foreign key to ::tableName().
	 *
	 * @return string
	 */
	public static function translationForeignKey(): string {
		return 'crt_entity';
	}

	/**
	 * The field name in the translations table that is the language code.
	 *
	 * @return string
	 */
	public static function translationLangField(): string {
		return 'crt_lang';
	}

	/**
	 * The full name of the tags database table.
	 *
	 * @return string
	 */
	public static function tagsTableName(): string {
		return 'communityrequests_tags';
	}

	/**
	 * Get a single wishlist entity.
	 *
	 * @param PageIdentity $page
	 * @param ?string $lang
	 * @param int $fetchWikitext See ::getAll() for possible values.
	 * @return ?AbstractWishlistEntity
	 */
	public function get(
		PageIdentity $page,
		?string $lang = null,
		int $fetchWikitext = self::FETCH_WIKITEXT_NONE,
	): ?AbstractWishlistEntity {
		if ( !$this->config->isWishOrFocusAreaPage( $page ) ) {
			return null;
		}

		$dbr = $this->dbProvider->getReplicaDatabase( 'virtual-communityrequests' );
		$data = $dbr->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->table( static::tableName() )
			->join(
				static::translationsTableName(),
				null,
				static::translationForeignKey() . ' = ' . static::pageField()
			)
			->fields( static::fields() )
			->where( [
				static::entityTypeField() => $this->entityTypeId(),
				static::pageField() => $page->getId(),
			] )
			->fetchResultSet();
		if ( !$data->count() ) {
			return null;
		}

		return $this->getEntitiesFromDbResultInternal( $data, $lang, $fetchWikitext )[0] ?? null;
	}

	/**
	 * Get a count of the wishlist entities in the database.
	 *
	 * @param array $filters Filters to apply to the query. Keys can be 'tags', 'statuses', or 'focusareas'.
	 * @return int
	 */
	public function getCount( array $filters = self::FILTER_NONE ): int {
		$dbr = $this->dbProvider->getReplicaDatabase( 'virtual-communityrequests' );
		$select = $dbr->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->select( 'COUNT(*)' )
			->from( static::tableName() )
			->where( [ static::entityTypeField() => $this->entityTypeId() ] );
		return (int)$this->applyFilters( $dbr, $select, $filters )->fetchField();
	}

	/**
	 * Apply filters to a database query.
	 * Child classes should override this to add whatever filters they need.
	 *
	 * @param IReadableDatabase $dbr
	 * @param SelectQueryBuilder $select
	 * @param mixed[] $filters
	 * @return SelectQueryBuilder
	 */
	protected function applyFilters(
		IReadableDatabase $dbr,
		SelectQueryBuilder $select,
		array $filters
	): SelectQueryBuilder {
		if ( isset( $filters[AbstractWishlistEntity::PARAM_STATUSES] ) &&
			$filters[AbstractWishlistEntity::PARAM_STATUSES]
		) {
			$statusIds = [];
			foreach ( $this->config->getStatuses() as $statusName => $statusInfo ) {
				if ( in_array( $statusName, $filters[AbstractWishlistEntity::PARAM_STATUSES] ) ) {
					$statusIds[] = $statusInfo['id'];
				}
			}
			$select->andWhere( [ 'cr_status' => $statusIds ] );
		} else {
			// if no status filter specified, exclude statuses marked as excluded
			$nonExcludedStatusIds = [];
			foreach ( $this->config->getStatuses() as $statusName => $statusInfo ) {
				if ( empty( $statusInfo['excluded'] ) ) {
					$nonExcludedStatusIds[] = $statusInfo['id'];
				}
			}
			if ( $nonExcludedStatusIds ) {
				$select->andWhere( [ 'cr_status' => $nonExcludedStatusIds ] );
			}
		}
		return $select;
	}

	/**
	 * Get a sorted list of wishlist entities in the given language.
	 *
	 * @param string $lang Requested language code.
	 * @param string $orderBy Use AbstractWishlistStore::*field() static methods.
	 * @param string $sort Use AbstractWishlistStore::SORT_ASC or AbstractWishlistStore::SORT_DESC.
	 * @param int $limit Limit the number of results.
	 * @param ?array $offset As produced by ApiBase::parseContinueParamOrDie().
	 * @param array $filters Filters to apply to the query. Keys can be 'tags', 'statuses', or 'focusareas'.
	 * @param int $fetchWikitext Which fields to fetch from wikitext. One of:
	 *   - self::FETCH_WIKITEXT_NONE - Default; the page content is not queried.
	 *   - self::FETCH_WIKITEXT_RAW - Return fields that could contain <translate> tags and include them as-is.
	 *   - self::FETCH_WIKITEXT_TRANSLATED - Query the translation subpage to return fields after being
	 *       processed by Extension:Translate, i.e. with <translate> tags stripped out.
	 * @return AbstractWishlistEntity[]
	 */
	public function getAll(
		string $lang,
		string $orderBy,
		string $sort = self::SORT_DESC,
		int $limit = 50,
		?array $offset = null,
		array $filters = self::FILTER_NONE,
		int $fetchWikitext = self::FETCH_WIKITEXT_NONE,
	): array {
		$dbr = $this->dbProvider->getReplicaDatabase( 'virtual-communityrequests' );
		$langs = array_unique( [
			$lang,
			...$this->languageFallback->getAll( $lang ),
		] );

		$orderPrecedence = match ( $orderBy ) {
			static::createdField() => [ static::createdField(), static::titleField(), static::voteCountField() ],
			static::updatedField() => [ static::updatedField(), static::titleField(), static::voteCountField() ],
			static::voteCountField() => [ static::voteCountField(), static::titleField(), static::createdField() ],
			default => [ static::titleField(), static::createdField(), static::voteCountField() ],
		};

		$sortDir = in_array( strtolower( $sort ), [ 'ascending', 'asc' ] ) ?
			static::SORT_ASC :
			static::SORT_DESC;

		$orderBy = $orderPrecedence;
		// Add page to the end of the sort columns, for repeatability.
		$orderBy[] = static::pageField();
		// For MySQL, cast to string for case-insensitive ordering.
		if ( $dbr->getType() === 'mysql' ) {
			$orderSql = 'CONVERT( ' . $dbr->addIdentifierQuotes( static::titleField() ) . ' USING utf8mb4 )';
			$orderBy[ array_search( static::titleField(), $orderBy ) ] = $orderSql;
		}

		$select = $dbr->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->table( static::tableName() )
			->join(
				static::translationsTableName(),
				null,
				static::translationForeignKey() . ' = ' . static::pageField() )
			->fields( static::fields() )
			->where( [ static::entityTypeField() => $this->entityTypeId() ] )
			->andWhere( $dbr->makeList( [
				static::translationLangField() => $langs,
				$dbr->makeList( [
					static::translationLangField() . '=' . static::baseLangField(),
					'NOT EXISTS (' .
						$dbr->newSelectQueryBuilder()
							->select( '1' )
							->from( static::translationsTableName(), 'transinner' )
							->where( [
								'transinner.' . static::translationLangField() => $langs,
								'transinner.' . static::translationForeignKey() . '=' . static::pageField()
							] )
							->limit( 1 )
							->caller( __METHOD__ )
							->getSQL()
						. ')'
				], $dbr::LIST_AND )
			], $dbr::LIST_OR ) )
			->orderBy( $orderBy, $sortDir )
			// Leave room for the fallback languages.
			->limit( $limit * ( count( $langs ) + 1 ) );

		// Apply filters, in this class and optionally its two children.
		$select = $this->applyFilters( $dbr, $select, $filters );

		if ( $offset ) {
			$conds = [];
			foreach ( $orderPrecedence as $field ) {
				$conds[$field] = match ( $field ) {
					// Timestamp
					static::createdField(), static::updatedField() => $offset[1],
					// Integer
					static::voteCountField() => $offset[2],
					// String (title)
					default => $offset[0],
				};
			}

			$select->andWhere(
				$dbr->buildComparison( $sortDir === static::SORT_DESC ? '<=' : '>=', $conds )
			);
		}

		$entities = $this->getEntitiesFromDbResultInternal(
			$select->fetchResultSet(),
			$lang,
			$fetchWikitext,
		);
		return array_slice( $entities, 0, $limit );
	}

	/**
	 * Create a list of AbstractWishlistEntity objects from the given result set.
	 *
	 * This method is also responsible for fetching any associated data.
	 *
	 * @param IReadableDatabase $dbr The database connection.
	 * @param array $rows Sorted DB rows containing static::fields().
	 * @param array $entityDataByPage DB rows grouped by page ID and language.
	 * @return AbstractWishlistEntity[]
	 */
	abstract protected function getEntitiesFromDbResult(
		IReadableDatabase $dbr,
		array $rows,
		array $entityDataByPage,
	): array;

	/**
	 * Groups results by page ID, using the first row with a matching language in the user's
	 * language and/or fallback chain, or finally the base language if no match is found.
	 *
	 * If wikitext is requested, it will be fetched from the base entity page or translation subpage,
	 * and added to the result rows with the keys as defined in ::getExtTranslateFields().
	 *
	 * Results are then fed through ::getEntitiesFromDbResult() to return AbstractWishlistEntity objects.
	 *
	 * @return AbstractWishlistEntity[]
	 */
	private function getEntitiesFromDbResultInternal(
		IResultWrapper $resultWrapper,
		?string $lang,
		int $fetchWikitext = self::FETCH_WIKITEXT_NONE,
	): array {
		if ( $resultWrapper->count() === 0 ) {
			return [];
		}

		$fallbackLangs = $lang === null ? [] : array_unique( [
			$lang,
			...$this->languageFallback->getAll( $lang ),
		] );

		// Do a first pass that just collects the page IDs.
		$pageIds = [];
		foreach ( $resultWrapper as $row ) {
			$pageIds[] = (int)$row->{static::pageField()};
		}
		// Fetch page titles and namespaces for those IDs.
		// This is done in a separate query since we can't join on the page table on the
		// WMF installation because our DB lives in different cluster than Core (T404124).
		$pagesTitleNsByIds = $this->getPageTitleNsFromIds( $pageIds );

		// Group the result set by entity page ID and language.
		$entityDataByPage = [];
		// Also store their original order, for later re-sorting.
		$originalOrder = [];
		foreach ( $resultWrapper as $entityData ) {
			$pageId = (int)$entityData->{static::pageField()};
			// Shouldn't happen under normal conditions, but tests may create entities without
			// a corresponding page, for example. This short-circuiting is essentially doing what
			// the JOIN on the page table would do (filtering out rows without a matching page).
			if ( !isset( $pagesTitleNsByIds[$pageId] ) ) {
				continue;
			}
			$langCode = $entityData->{static::translationLangField()};
			$entityData->page_namespace = (int)$pagesTitleNsByIds[$pageId]->page_namespace;
			$entityData->page_title = $pagesTitleNsByIds[$pageId]->page_title;

			// Add in the wikitext fields if requested.
			if ( $fetchWikitext !== self::FETCH_WIKITEXT_NONE ) {
				$this->setWikitextFieldsForDbResult( $entityData, $fetchWikitext );
			}
			$entityDataByPage[$pageId][$langCode] = $entityData;
			$originalOrder[$pageId . $langCode] = true;
		}

		$rows = [];
		foreach ( $entityDataByPage as $entityDataByLang ) {
			// All rows will have the same base language.
			$firstRow = reset( $entityDataByLang );
			$baseLang = $firstRow->{static::baseLangField()};
			$row = null;

			// Find the first row with a matching language.
			foreach ( $fallbackLangs as $fallbackLang ) {
				if ( isset( $entityDataByLang[$fallbackLang] ) ) {
					$row = $entityDataByLang[$fallbackLang];
					break;
				}
			}

			// If no row in the given language (or any of its fallback langauges) was found,
			// default to the base language or otherwise the first row.
			if ( !$row ) {
				$row = $entityDataByLang[ $baseLang ] ?? $firstRow;
			}
			// Array key here matches what's used in $originalOrder above.
			$rows[$row->{static::pageField()} . $row->{static::translationLangField()}] = $row;
		}

		// Re-sort $rows to match the original order of the result set.
		$sortedRows = [];
		foreach ( $originalOrder as $originalKey => $o ) {
			if ( isset( $rows[$originalKey] ) ) {
				$sortedRows[] = $rows[$originalKey];
			}
		}

		return $this->getEntitiesFromDbResult(
			$this->dbProvider->getReplicaDatabase( 'virtual-communityrequests' ),
			$sortedRows,
			$entityDataByPage
		);
	}

	private function getPageTitleNsFromIds( array $pageIds ): array {
		$pageData = $this->dbProvider->getReplicaDatabase()
			->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->table( 'page' )
			->fields( [ 'page_id', 'page_namespace', 'page_title' ] )
			->where( [ 'page_id' => $pageIds ] )
			->fetchResultSet();
		$pageDataById = [];
		foreach ( $pageData as $pageRow ) {
			$pageDataById[(int)$pageRow->page_id] = $pageRow;
		}
		return $pageDataById;
	}

	private function setWikitextFieldsForDbResult( stdClass $entityData, int $fetchWikitext ): void {
		// For self::FETCH_WIKITEXT_RAW
		$translationPageId = (int)$entityData->{static::pageField()};
		// Use a PageReference for logging.
		$translationPageRef = PageReferenceValue::localReference(
			(int)$entityData->page_namespace,
			$entityData->page_title
		);

		// For self::FETCH_WIKITEXT_TRANSLATED, use the page ID of the translation subpage.
		if ( $fetchWikitext === self::FETCH_WIKITEXT_TRANSLATED ) {
			$translationPageRef = PageReferenceValue::localReference(
				(int)$entityData->page_namespace,
				$entityData->page_title . '/' . $entityData->{static::translationLangField()}
			);
			$translationPage = $this->pageStore->getPageByReference( $translationPageRef );
			if ( $translationPage ) {
				// If the translation subpage exists, use it, otherwise fallback to the base page.
				$translationPageId = $translationPage->getId();
			}
		}

		// Fetch wikitext data from the page and merge it into the row.
		$wikitextData = $this->getDataFromPageId( $translationPageId );
		foreach ( $this->getMappedFields() as $field => $property ) {
			// Strip <translate> tags for self::FETCH_WIKITEXT_TRANSLATED,
			// in case $translationPageId is the base page (translation subpage missing).
			if ( $fetchWikitext === self::FETCH_WIKITEXT_TRANSLATED &&
				in_array( $field, $this->getExtTranslateFields() ) &&
				isset( $wikitextData[$field] ) &&
				$this->translatablePageParser?->containsMarkup( $wikitextData[$field] )
			) {
				$this->logger->debug(
					__METHOD__ . ': Stripping <translate> tags from field {0} of entity {1}',
					[ $field, $translationPageRef->__toString() ]
				);
				$wikitextData[$field] = $this->translatablePageParser->cleanupTags( $wikitextData[$field] );
			}

			$entityData->$property = $wikitextData[$field] ?? '';
		}

		$this->logger->debug(
			__METHOD__ . ': Fetched wikitext for entity {0} lang {1} with data {2}',
			[
				$translationPageRef->__toString(),
				$entityData->{static::translationLangField()},
				json_encode( $wikitextData )
			]
		);
	}

	/**
	 * Save a wishlist entity.
	 * This is expected to call ::saveTranslations().
	 *
	 * @param AbstractWishlistEntity $entity
	 * @throws InvalidArgumentException If the wishlist page has not been added to the database yet.
	 */
	abstract public function save( AbstractWishlistEntity $entity ): void;

	/**
	 * Get a new entity ID using the IdGenerator.
	 */
	abstract public function getNewId(): int;

	/**
	 * Save the translations for a wishlist entity.
	 */
	protected function saveTranslations(
		AbstractWishlistEntity $entity,
		IDatabase $dbw,
		array $dataSet = []
	): void {
		$data = [
			static::translationForeignKey() => $entity->getPage()->getId(),
			static::translationLangField() => $entity->getLang()
		];
		$dataSet[static::titleField()] = mb_strcut( $entity->getTitle(), 0, static::TITLE_MAX_BYTES, 'UTF-8' );
		$dbw->newInsertQueryBuilder()
			->insert( static::translationsTableName() )
			->rows( [ array_merge( $data, $dataSet ) ] )
			->set( $dataSet )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ static::translationForeignKey(), static::translationLangField() ] )
			->caller( __METHOD__ )
			->execute();
		$this->logger->debug(
			__METHOD__ . ': Saved translations for entity {0} with data {1}',
			[ $entity->getPage()->__toString(), json_encode( array_merge( $data, $dataSet ) ) ]
		);
	}

	/**
	 * Delete a wish or focus area and all its associated data.
	 * Called from CommunityRequestsHooks::onPageDeleteComplete().
	 *
	 * @param AbstractWishlistEntity $entity
	 * @param array $assocData Associated data to be deleted when the wish or focus area
	 *   is deleted entirely (and not just one of its translations). Keys are table names,
	 *   values are the foreign key field names in those tables that point to the page ID.
	 * @return IDatabase
	 */
	public function delete( AbstractWishlistEntity $entity, array $assocData = [] ): IDatabase {
		$dbw = $this->dbProvider->getPrimaryDatabase( 'virtual-communityrequests' );
		$dbw->startAtomic( __METHOD__ );

		// First delete translations.
		$delTranslations = $dbw->newDeleteQueryBuilder()
			->deleteFrom( static::translationsTableName() )
			->where( [ static::translationForeignKey() => $entity->getPage()->getId() ] );

		// Delete only for the given language, if not the base language.
		if ( $entity->getLang() !== $entity->getBaseLang() ) {
			$delTranslations->andWhere( [ static::translationLangField() => $entity->getLang() ] );
		}

		$delTranslations->caller( __METHOD__ )
			->execute();

		// Delete everything else if we're dealing with the base language.
		if ( $entity->isBaseLang() ) {
			$assocData[static::tableName()] = static::pageField();
			foreach ( $assocData as $table => $foreignKey ) {
				$dbw->newDeleteQueryBuilder()
					->deleteFrom( $table )
					->where( [ $foreignKey => $entity->getPage()->getId() ] )
					->caller( __METHOD__ )
					->execute();
			}
		}

		$dbw->endAtomic( __METHOD__ );
		return $dbw;
	}

	/**
	 * Set the language of a wish or focus area page.
	 * This method updates the page_lang field in the page table.
	 *
	 * @fixme We should have the user set the language correctly from the beginning, see T409992
	 */
	public function setPageLanguage( int $pageId, string $newLang ): void {
		$dbw = $this->dbProvider->getPrimaryDatabase();
		$dbw->newUpdateQueryBuilder()
			->update( 'page' )
			->set( [ 'page_lang' => $newLang ] )
			->where( [ 'page_id' => $pageId ] )
			->caller( __METHOD__ )->execute();
		// Force re-render of the page so that language-based content (parser functions etc.) get updated.
		Title::newFromID( $pageId )->invalidateCache();
	}

	/**
	 * Extract the wish or focus area ID from a user input string.
	 *
	 * This method accepts the full page title,
	 * a prefixed ID (e.g., "W123"), or just the ID itself.
	 *
	 * @param string|int|null $input
	 * @return ?int
	 */
	public function getIdFromInput( string|int|null $input ): ?int {
		if ( $input === null ) {
			return null;
		}
		if ( is_int( $input ) ) {
			return $input;
		}
		if ( str_starts_with( $input, $this->getPagePrefix() ) ) {
			$input = substr( $input, strlen( $this->getPagePrefix() ) );
		}
		return (int)preg_replace( '/[^0-9]/', '', $input ) ?: null;
	}

	/**
	 * Parse wikitext of a wish or focus area parser function invocation into an array of data.
	 *
	 * @param WikitextContent $content
	 * @return ?array
	 */
	public function getDataFromWikitextContent( WikitextContent $content ): ?array {
		return ( new ArgumentExtractor( $this->parserFactory ) )
			->getFuncArgs(
				'communityrequests',
				$this->entityType(),
				$content->getText()
			);
	}

	/**
	 * Parse wikitext content of a wish or focus area page given its page ID.
	 *
	 * @param int $pageId The parsed data from the wikitext.
	 * @return ?array Includes the latest revision ID of the entity page with
	 *   the key AbstractWishlistEntity::PARAM_BASE_REV_ID.
	 * @throws RuntimeException If the content type is not WikitextContent or
	 *   if the page otherwise could not be parsed.
	 */
	public function getDataFromPageId( int $pageId ): ?array {
		$revRecord = $this->revisionStore->getRevisionByPageId( $pageId );
		$content = $revRecord->getMainContentRaw();
		if ( !$content instanceof WikitextContent ) {
			throw new RuntimeException( 'Invalid content type for AbstractWishlistEntity' );
		}
		$args = $this->getDataFromWikitextContent( $content );
		if ( !$args ) {
			throw new RuntimeException( "Failed to load wikitext data for page ID $pageId" );
		}
		$args[AbstractWishlistEntity::PARAM_BASE_REV_ID] = $revRecord->getId();
		return $args;
	}

	/**
	 * Get a mapping of translatable or wikitext-only fields to their "virtual" column names
	 * for easier referencing in subclass implementations of ::getEntitiesFromDbResult().
	 *
	 * Keys are AbstractWishlistEntity::PARAM_* constants, and values are database column names
	 * that are merged into the DB result set.
	 *
	 * Effectively all ::getExtTranslateFields() and ::getWikitextFields() fields should be
	 * included here.
	 *
	 * @return string[]
	 */
	abstract public function getMappedFields(): array;

	/**
	 * Get the fields that could be tagged for translation with Extension:Translate,
	 * and thus should be fetched from wikitext instead of the CommunityRequests tables
	 * when WishStore::getAll() is called with self::FETCH_WIKITEXT_TRANSLATED.
	 *
	 * @return string[]
	 */
	abstract public function getExtTranslateFields(): array;

	/**
	 * Fields that ONLY live in wikitext, and not in CommunityRequests DB tables.
	 *
	 * This used to aid performance of the API by avoiding unnecessary querying of page content.
	 *
	 * @return string[] AbstractWishlistEntity::PARAM_* constants.
	 */
	abstract public function getWikitextFields(): array;

	/**
	 * Get the parameters for the parser function invocation.
	 */
	abstract public function getParams(): array;

	/**
	 * Get the parameters for the parser function who values are array-like.
	 * - In the APIs, values are transformed to be pipe-separated.
	 * - In the wikitext, values are comma-separated.
	 * - In the Special forms, values are one-dimensional arrays.
	 */
	abstract public function getArrayParams(): array;

	/**
	 * Normalize array-like parameter values into the desired format.
	 *
	 * @param array $data Full entity data as an associative array.
	 * @param ?string $delimiter One of:
	 *   - self::ARRAY_DELIMITER_SPECIAL (null) to return an array of strings,
	 *   - self::ARRAY_DELIMITER_WIKITEXT (comma) to return a comma-separated string,
	 *   - self::ARRAY_DELIMITER_API (pipe) to return a pipe-separated string.
	 * @return array The modified $data array with array-like values normalized.
	 */
	public function normalizeArrayValues(
		array $data,
		?string $delimiter = self::ARRAY_DELIMITER_SPECIAL
	): array {
		foreach ( $this->getArrayParams() as $param ) {
			$values = $data[$param] ?? '';
			$fromDelimiter = self::ARRAY_DELIMITER_WIKITEXT;
			if ( is_array( $values ) ) {
				$values = implode( self::ARRAY_DELIMITER_WIKITEXT, $values );
			} elseif ( str_contains( $values, self::ARRAY_DELIMITER_API ) ) {
				$fromDelimiter = self::ARRAY_DELIMITER_API;
			}
			$values = array_filter( explode( $fromDelimiter, $values ) );
			if ( $delimiter ) {
				$data[$param] = implode( $delimiter, $values );
			} else {
				$data[$param] = $values;
			}
		}
		return $data;
	}

	/**
	 * Get the prefix for the wishlist entity page.
	 */
	abstract public function getPagePrefix(): string;
}
