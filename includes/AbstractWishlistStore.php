<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use InvalidArgumentException;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Page\PageIdentity;
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

	public function __construct(
		protected IConnectionProvider $dbProvider,
		protected LanguageFallback $languageFallback,
		protected RevisionStore $revisionStore,
		protected ParserFactory $parserFactory,
		protected TitleParser $titleParser,
		protected TitleFormatter $titleFormatter,
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
	 * @return ?AbstractWishlistEntity
	 */
	public function get( PageIdentity $page, ?string $lang = null ): ?AbstractWishlistEntity {
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

		return $this->getEntitiesFromLangFallbacks( $dbr, $data, $lang )[0] ?? null;
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
	 * @return array
	 */
	public function getAll(
		string $lang,
		string $orderBy,
		string $sort = self::SORT_DESC,
		int $limit = 50,
		?array $offset = null
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

		$select = $dbr->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->table( static::tableName() )
			->join( 'page', null, [ static::pageField() . ' = page_id' ] )
			->join(
				static::translationsTableName(),
				null,
				static::translationForeignKey() . ' = ' . static::pageField() )
			->fields( static::fields() )
			// FIXME: uses filesort; We probably need an index for
			->where( $dbr->makeList( [
				static::translationLangField() => $langs,
				static::translationLangField() . '=' . static::baseLangField()
			], $dbr::LIST_OR ) )
			->orderBy(
				$orderPrecedence,
				$sort === static::SORT_DESC ? static::SORT_DESC : static::SORT_ASC
			)
			// Leave room for the fallback languages.
			->limit( $limit * ( count( $langs ) + 1 ) );

		if ( $offset ) {
			$conds = [];
			foreach ( $orderPrecedence as $field ) {
				$conds[ $field ] = match ( $field ) {
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
				$dbr->buildComparison( $sort === static::SORT_DESC ? '<=' : '>=', $conds )
			);
		}

		$entities = $this->getEntitiesFromLangFallbacks( $dbr, $select->fetchResultSet(), $lang );
		return array_slice( $entities, 0, $limit );
	}

	/**
	 * Create a list of AbstractWishlistEntity objects from the given result set, grouping by page ID
	 * and using the first row with a matching language in the user's language and/or fallback chain,
	 * or finally the base language if no match is found.
	 *
	 * This method is also responsible for fetching any associated data.
	 *
	 * @param IReadableDatabase $dbr The database connection.
	 * @param IResultWrapper $resultWrapper The DB result wrapper.
	 * @param ?string $lang The requested language code. Null to use the base language.
	 * @return AbstractWishlistEntity[]
	 */
	abstract protected function getEntitiesFromLangFallbacks(
		IReadableDatabase $dbr,
		IResultWrapper $resultWrapper,
		?string $lang = null
	): array;

	/**
	 * @param IResultWrapper $resultWrapper
	 * @param ?string $lang
	 * @return array with members:
	 *   - (array): DB rows containing static::fields()
	 *   - (array): DB rows grouped by page ID and language.
	 */
	protected function getEntitiesFromLangFallbacksInternal(
		IResultWrapper $resultWrapper,
		?string $lang = null
	): array {
		$fallbackLangs = $lang === null ? [] : array_unique( [
			$lang,
			...$this->languageFallback->getAll( $lang ),
		] );

		$rows = [];
		// Group the result set by wish page ID and language.
		$entityDataByPage = [];
		foreach ( $resultWrapper as $entityData ) {
			$pageId = $entityData->{ static::pageField() };
			$langCode = $entityData->{ static::translationLangField() };
			$entityDataByPage[ $pageId ][ $langCode ] = $entityData;
		}

		foreach ( $entityDataByPage as $entityDataByLang ) {
			// All rows in $entityData have the same base language.
			$baseLang = reset( $entityDataByLang )->{ static::baseLangField() };
			// This will be overridden if a user-preferred language is found.
			$row = $entityDataByLang[ $baseLang ];

			// Find the first row with a matching language.
			foreach ( $fallbackLangs as $fallbackLang ) {
				if ( isset( $entityDataByLang[ $fallbackLang ] ) ) {
					$row = $entityDataByLang[ $fallbackLang ];
					break;
				}
			}

			$rows[] = $row;
		}

		return [ $rows, $entityDataByPage ];
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
	 * Get a new wish ID using the IdGenerator.
	 *
	 * @return int The new wish ID.
	 */
	abstract public function getNewId(): int;

	/**
	 * Save the translations for a wishlist entity.
	 *
	 * @param AbstractWishlistEntity $entity
	 * @param IDatabase $dbw
	 * @param array $dataSet
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
		$dataSet[ static::titleField() ] = mb_strcut( $entity->getTitle(), 0, static::TITLE_MAX_BYTES, 'UTF-8' );
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
			$assocData[ static::tableName() ] = static::pageField();
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
	 * @param int $pageId
	 * @param string $newLang
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
	 * Parse wish data from the template invocation of a wishlist entity page.
	 *
	 * @param int $pageId The page ID of the wish or focus area.
	 * @return ?array The parsed data from the wikitext.
	 *   This includes a 'baseRevId' key with the latest revision ID of the wish page.
	 * @throws RuntimeException If the content type is not WikitextContent.
	 */
	public function getDataFromWikitext( int $pageId ): ?array {
		$revRecord = $this->revisionStore->getRevisionByPageId( $pageId );
		$content = $revRecord->getMainContentRaw();
		if ( !$content instanceof WikitextContent ) {
			throw new RuntimeException( 'Invalid content type for AbstractWishlistEntity' );
		}
		$wikitext = $content->getText();
		$args = ( new TemplateArgumentExtractor( $this->parserFactory ) )
			->getFuncArgs(
				'communityrequests',
				$this->entityType(),
				$wikitext
			);
		if ( $args !== null ) {
			// Include baseRevId
			$args[ 'baseRevId' ] = $revRecord->getId();
		}
		return $args;
	}

	/**
	 * Get the fields that only exist in the wikitext template invocation,
	 * and should be extracted with ::getDataFromWikitext().
	 *
	 * @return string[]
	 */
	abstract public function getWikitextFields(): array;

	/**
	 * Get the parameters for the template invocation of the wishlist entity.
	 *
	 * @return array
	 */
	abstract public function getTemplateParams(): array;

	/**
	 * Get the prefix for the wishlist entity page.
	 *
	 * @return string
	 */
	abstract public function getPagePrefix(): string;
}
