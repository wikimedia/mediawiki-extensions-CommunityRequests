<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use InvalidArgumentException;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Page\PageStore;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleParser;
use RuntimeException;
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
	public const SORT_ASC = SelectQueryBuilder::SORT_ASC;
	public const SORT_DESC = SelectQueryBuilder::SORT_DESC;
	public const FILTER_NONE = null;

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
	) {
	}

	/**
	 * Get the entity type for this store, either "wish" or "focus-area"
	 *
	 * @return string
	 */
	abstract public function entityType(): string;

	/**
	 * Get the table name for the wishlist entity.
	 *
	 * @return string
	 */
	abstract protected static function tableName(): string;

	/**
	 * Get the field names that should be used in SELECT queries.
	 *
	 * @return string[]
	 */
	abstract public static function fields(): array;

	/**
	 * Get the field names for the page field of ::tableName().
	 *
	 * @return string
	 */
	abstract protected static function pageField(): string;

	/**
	 * The field name for the creation timestamp.
	 *
	 * @return string
	 */
	abstract public static function createdField(): string;

	/**
	 * The field name for the last updated timestamp.
	 *
	 * @return string
	 */
	abstract public static function updatedField(): string;

	/**
	 * The field name for the vote count.
	 *
	 * @return string
	 */
	abstract public static function voteCountField(): string;

	/**
	 * The field name for the base language.
	 *
	 * @return string
	 */
	abstract protected static function baseLangField(): string;

	/**
	 * The field name for the translated title of the wishlist entity.
	 *
	 * @return string
	 */
	abstract public static function titleField(): string;

	/**
	 * Get the table name for the translations of wishlist entities.
	 *
	 * @return string
	 */
	abstract protected static function translationsTableName(): string;

	/**
	 * The field name in the translations table that is the foreign key to ::tableName().
	 *
	 * @return string
	 */
	abstract protected static function translationForeignKey(): string;

	/**
	 * The field name in the translations table that is the language code.
	 *
	 * @return string
	 */
	abstract protected static function translationLangField(): string;

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

		$dbr = $this->dbProvider->getReplicaDatabase();
		$data = $dbr->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->table( static::tableName() )
			->join( 'page', null, [ static::pageField() . ' = page_id' ] )
			->join(
				static::translationsTableName(),
				null,
				static::translationForeignKey() . ' = ' . static::pageField()
			)
			->fields( static::fields() )
			->where( [ static::pageField() => $page->getId() ] )
			->fetchResultSet();
		if ( !$data->count() ) {
			return null;
		}

		return $this->getEntitiesFromDbResultInternal( $data, $lang, $fetchWikitext )[0] ?? null;
	}

	/**
	 * Get a count of the wishlist entities in the database.
	 *
	 * @return int
	 */
	public function getCount(): int {
		$dbr = $this->dbProvider->getReplicaDatabase();
		return (int)$dbr->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->select( 'COUNT(*)' )
			->from( static::tableName() )
			->fetchField();
	}

	/**
	 * Get a sorted list of wishlist entities in the given language.
	 *
	 * @param string $lang Requested language code.
	 * @param string $orderBy Use AbstractWishlistStore::*field() static methods.
	 * @param string $sort Use AbstractWishlistStore::SORT_ASC or AbstractWishlistStore::SORT_DESC.
	 * @param int $limit Limit the number of results.
	 * @param ?array $offset As produced by ApiBase::parseContinueParamOrDie().
	 * @param ?array $filters Unused; reserved for future use.
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
		?array $filters = self::FILTER_NONE,
		int $fetchWikitext = self::FETCH_WIKITEXT_NONE,
	): array {
		$dbr = $this->dbProvider->getReplicaDatabase();
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

		$sortDir = in_array( strtolower( $sort ), [ 'descending', 'desc' ] ) ?
				static::SORT_DESC :
				static::SORT_ASC;

		$select = $dbr->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->table( static::tableName() )
			->join( 'page', null, [ static::pageField() . ' = page_id' ] )
			->join(
				static::translationsTableName(),
				null,
				static::translationForeignKey() . ' = ' . static::pageField() )
			->fields( static::fields() )
			->where( $dbr->makeList( [
				static::translationLangField() => $langs,
				static::translationLangField() . '=' . static::baseLangField()
			], $dbr::LIST_OR ) )
			->orderBy( $orderPrecedence, $sortDir )
			// Leave room for the fallback languages.
			->limit( $limit * ( count( $langs ) + 1 ) );

		if ( $offset ) {
			$conds = [];
			foreach ( $orderPrecedence as $field ) {
				$conds[$field] = match ( $field ) {
					// Timestamp
					static::createdField() => $offset[1],
					static::updatedField() => $offset[1],
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
			$sortDir,
			$orderPrecedence,
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
		string $sort = self::SORT_DESC,
		array $orderPrecedence = [],
	): array {
		$fallbackLangs = $lang === null ? [] : array_unique( [
			$lang,
			...$this->languageFallback->getAll( $lang ),
		] );

		$rows = [];
		// Group the result set by entity page ID and language.
		$entityDataByPage = [];
		foreach ( $resultWrapper as $entityData ) {
			$pageId = (int)$entityData->{ static::pageField() };
			$langCode = $entityData->{ static::translationLangField() };

			// Fetch wikitext fields if requested.
			if ( $fetchWikitext ) {
				// For self::FETCH_WIKITEXT_RAW
				$translationPageId = $pageId;

				// For self::FETCH_WIKITEXT_TRANSLATED, use the page ID of the translation subpage.
				if ( $fetchWikitext === self::FETCH_WIKITEXT_TRANSLATED ) {
					$translationPageRef = PageReferenceValue::localReference(
						(int)$entityData->page_namespace,
						$entityData->page_title . '/' . $entityData->{static::baseLangField()}
					);
					$translationPageId = $this->pageStore->getPageByReference( $translationPageRef )?->getId()
						?? $translationPageId;
				}

				// Fetch wikitext data from the page and merge it into the row.
				$wikitextData = $this->getDataFromPageId( $translationPageId );
				foreach ( $this->getExtTranslateFields() as $field => $property ) {
					$entityData->$property = $wikitextData[$field] ?? '';
				}
			}

			$entityDataByPage[$pageId][$langCode] = $entityData;
		}

		foreach ( $entityDataByPage as $entityDataByLang ) {
			// All rows will have the same base language.
			$baseLang = reset( $entityDataByLang )->{ static::baseLangField() };
			// This will be overridden if a user-preferred language is found.
			$row = $entityDataByLang[$baseLang];

			// Find the first row with a matching language.
			foreach ( $fallbackLangs as $fallbackLang ) {
				if ( isset( $entityDataByLang[$fallbackLang] ) ) {
					$row = $entityDataByLang[$fallbackLang];
					break;
				}
			}

			$rows[] = $row;
		}

		// Re-sort $rows to match the original order of the result set.
		if ( $orderPrecedence ) {
			usort( $rows, static function ( $a, $b ) use ( $orderPrecedence, $sort ) {
				$sorterArrayA = array_map( static fn ( $field ) => $a->$field, $orderPrecedence );
				$sorterArrayB = array_map( static fn ( $field ) => $b->$field, $orderPrecedence );
				$comparison = $sorterArrayA <=> $sorterArrayB;
				if ( $comparison !== 0 ) {
					return $sort === self::SORT_DESC ? -$comparison : $comparison;
				}
				return 0;
			} );
		}

		return $this->getEntitiesFromDbResult(
			$this->dbProvider->getReplicaDatabase(),
			$rows,
			$entityDataByPage
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
		$dbw = $this->dbProvider->getPrimaryDatabase();
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
	 */
	public function setPageLanguage( int $pageId, string $newLang ): void {
		$dbw = $this->dbProvider->getPrimaryDatabase();
		$dbw->newUpdateQueryBuilder()
			->update( 'page' )
			->set( [ 'page_lang' => $newLang ] )
			->where( [ 'page_id' => $pageId ] )
			->caller( __METHOD__ )->execute();
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
	 * Get the fields that either only live in wikitext (not stored in CommunityRequest DB tables),
	 * or fields that could be tagged for translation with Extension:Translate,
	 * and thus should be fetched from wikitext instead of the CommunityRequests tables
	 * when WishStore::getAll() is called with self::FETCH_WIKITEXT_TRANSLATED.
	 *
	 * Effectively, any field that could contain <translate> tags should be listed here.
	 *
	 * Keys are AbstractWishlistEntity::PARAM_* constants, and values are database column names
	 * that are merged into the DB result set. The values may be "virtual" column names for
	 * easier referencing in the subclass implementation of ::getEntitiesFromDbResult().
	 *
	 * @return string[]
	 */
	abstract public function getExtTranslateFields(): array;

	/**
	 * Fields that ONLY live in wikitext, and not in CommunityRequests DB tables.
	 *
	 * This is a subset of getWikitextFields() to aid performance of the API
	 * by avoiding unnecessary querying of page content.
	 *
	 * @return string[] AbstractWishlistEntity::PARAM_* constants.
	 */
	abstract public function getWikitextFields(): array;

	/**
	 * Get the parameters for the parser function invocation.
	 */
	abstract public function getParams(): array;

	/**
	 * Get the prefix for the wishlist entity page.
	 */
	abstract public function getPagePrefix(): string;
}
