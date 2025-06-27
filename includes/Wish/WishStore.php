<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use InvalidArgumentException;
use MediaWiki\Content\WikitextContent;
use MediaWiki\DAO\WikiAwareEntity;
use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleParser;
use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * WishStore is responsible for all database operations related to wishes.
 *
 * @note "Wish" is the user-facing term, while "request" is used in storage.
 */
class WishStore {

	public const ORDER_BY_CREATION = 'cr_created';
	public const ORDER_BY_UPDATED = 'cr_updated';
	public const ORDER_BY_VOTE_COUNT = 'cr_vote_count';
	public const SORT_ASC = SelectQueryBuilder::SORT_ASC;
	public const SORT_DESC = SelectQueryBuilder::SORT_DESC;

	/**
	 * Fields needed to construct a Wish object.
	 *
	 * @var array<string>
	 */
	private const WISH_FIELDS = [
		'page_namespace',
		'page_title',
		'cr_page',
		'cr_type',
		'cr_status',
		'cr_focus_area',
		'cr_actor',
		'cr_vote_count',
		'cr_base_lang',
		'cr_created',
		'cr_updated',
		'crt_title',
		'crt_other_project',
		'crt_lang',
	];

	public function __construct(
		private readonly ActorNormalization $actorNormalization,
		private readonly IConnectionProvider $dbProvider,
		private readonly UserFactory $userFactory,
		private readonly LanguageFallback $languageFallback,
		private readonly RevisionStore $revisionStore,
		private readonly ParserFactory $parserFactory,
		private readonly TitleParser $titleParser,
		private readonly TitleFormatter $titleFormatter,
		private readonly IdGenerator $idGenerator,
		private readonly WishlistConfig $config
	) {
	}

	/**
	 * Save a single wish to the database.
	 *
	 * @param Wish $wish The wish to save.
	 * @throws InvalidArgumentException If the wish page has not been added to the database yet,
	 *   or we're creating a new wish without a proposer.
	 */
	public function save( Wish $wish ): void {
		if ( !$wish->getPage()->getId() ) {
			throw new InvalidArgumentException( 'Wish page has not been added to the database yet!' );
		}

		$dbw = $this->dbProvider->getPrimaryDatabase();
		$dbw->startAtomic( __METHOD__ );

		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Proposer is checked and not null
		$proposer = $wish->getProposer() ? $this->actorNormalization->findActorId( $wish->getProposer(), $dbw ) : null;
		$created = $wish->getCreated();

		if ( !$proposer || !$created ) {
			// Fetch proposer and creation date from the wishes table.
			$proposerCreated = $dbw->newSelectQueryBuilder()
				->caller( __METHOD__ )
				->from( 'communityrequests_wishes' )
				->fields( [ 'cr_actor', 'cr_created' ] )
				->where( [ 'cr_page' => $wish->getPage()->getId() ] )
				->forUpdate()
				->fetchRow();
			if ( $proposerCreated ) {
				$proposer ??= $proposerCreated->cr_actor;
				$created ??= $proposerCreated->cr_created;
			}
		}
		if ( !$proposer ) {
			throw new InvalidArgumentException( 'Wishes must have a proposer!' );
		}
		if ( !$created ) {
			throw new InvalidArgumentException( 'Wishes must have a created date!' );
		}

		$data = [
			'cr_page' => $wish->getPage()->getId(),
			'cr_base_lang' => $wish->getBaseLanguage(),
		];
		$dataSet = [
			'cr_actor' => $proposer,
			'cr_type' => $wish->getType(),
			'cr_status' => $wish->getStatus(),
			'cr_focus_area' => $wish->getFocusAreaId(),
			'cr_created' => $dbw->timestamp( $created ),
			'cr_updated' => $dbw->timestamp( $wish->getUpdated() ?: wfTimestampNow() ),
		];
		$dbw->newInsertQueryBuilder()
			->insert( 'communityrequests_wishes' )
			->rows( [ array_merge( $data, $dataSet ) ] )
			->set( $dataSet )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'cr_page' ] )
			->caller( __METHOD__ )
			->execute();

		$this->saveTranslations( $wish, $dbw );
		$this->saveProjectsAndPhabTasks( $wish, $dbw );

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Save the translations for a wish.
	 *
	 * @param Wish $wish The wish to save.
	 * @param IDatabase $dbw The database connection.
	 */
	private function saveTranslations( Wish $wish, IDatabase $dbw ): void {
		$data = [ 'crt_wish' => $wish->getPage()->getId(), 'crt_lang' => $wish->getLanguage() ];
		$dataSet = [
			'crt_title' => $wish->getTitle(),
			'crt_other_project' => $wish->getOtherProject() ?: null,
		];
		$dbw->newInsertQueryBuilder()
			->insert( 'communityrequests_wishes_translations' )
			->rows( [ array_merge( $data, $dataSet ) ] )
			->set( $dataSet )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'crt_wish', 'crt_lang' ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Save the projects and Phabricator tasks associated with a wish.
	 *
	 * @param Wish $wish The wish to save.
	 * @param IDatabase $dbw The database connection.
	 */
	private function saveProjectsAndPhabTasks( Wish $wish, IDatabase $dbw ): void {
		$queryMetadata = [
			[
				'table' => 'communityrequests_projects',
				'key' => 'crp_project',
				'foreignKey' => 'crp_wish',
				'wishMethod' => 'getProjects',
			], [
				'table' => 'communityrequests_phab_tasks',
				'key' => 'crpt_task_id',
				'foreignKey' => 'crpt_wish',
				'wishMethod' => 'getPhabTasks',
			]
		];

		foreach ( $queryMetadata as $metadata ) {
			// First re-fetch any existing rows so we know which ones to delete.
			$existing = $dbw->newSelectQueryBuilder()
				->caller( __METHOD__ )
				->table( $metadata['table'] )
				->fields( [ $metadata['key'] ] )
				->where( [ $metadata['foreignKey'] => $wish->getPage()->getId() ] )
				->fetchFieldValues();
			$instanceRows = $wish->{$metadata['wishMethod']}();

			// Delete any rows that are no longer associated with the wish.
			$toDelete = array_diff( $existing, $instanceRows );
			if ( count( $toDelete ) > 0 ) {
				$dbw->newDeleteQueryBuilder()
					->deleteFrom( $metadata['table'] )
					->where( [
						$metadata['foreignKey'] => $wish->getPage()->getId(),
						$metadata['key'] => $toDelete,
					] )
					->caller( __METHOD__ )
					->execute();
			}

			// Determine which new rows to insert, if any.
			$toInsert = array_diff( $instanceRows, $existing );
			if ( count( $toInsert ) === 0 ) {
				continue;
			}

			// Insert the new rows.
			$newRows = [];
			foreach ( $toInsert as $value ) {
				$newRows[] = [
					$metadata['foreignKey'] => $wish->getPage()->getId(),
					$metadata['key'] => $value,
				];
			}
			$dbw->newInsertQueryBuilder()
				->insert( $metadata['table'] )
				->rows( $newRows )
				->caller( __METHOD__ )
				->execute();
		}
	}

	/**
	 * Get a single Wish given a PageIdentity.
	 *
	 * @param PageIdentity $pageTitle The title of the wish.
	 * @param ?string $langCode Requested language code. If null, the base language is used.
	 * @return ?Wish null if the wish does not exist.
	 */
	public function getWish( PageIdentity $pageTitle, ?string $langCode = null ): ?Wish {
		if ( !$this->isWishPage( $pageTitle ) ) {
			return null;
		}

		$dbr = $this->dbProvider->getReplicaDatabase();
		$data = $dbr->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->table( 'communityrequests_wishes' )
			->join( 'page', null, [ 'cr_page = page_id' ] )
			->join( 'communityrequests_wishes_translations', null, 'crt_wish = cr_page' )
			->fields( self::WISH_FIELDS )
			->where( [ 'cr_page' => $pageTitle->getId() ] )
			->fetchResultSet();
		if ( !$data->count() ) {
			return null;
		}

		return $this->getWishesFromLangFallbacks( $dbr, $data, $langCode )[0] ?? null;
	}

	/**
	 * Get a sorted list of wishes for the given language.
	 *
	 * @param string $langCode Requested language code.
	 * @param string $orderBy Use WishStore::ORDER_BY_* constants.
	 * @param string $direction Use WishStore::SORT_ASC or WishStore::SORT_DESC.
	 * @param ?int $limit Limit the number of results.
	 * @return array<Wish>
	 */
	public function getWishes(
		string $langCode,
		string $orderBy = self::ORDER_BY_CREATION,
		string $direction = self::SORT_DESC,
		?int $limit = 50
	): array {
		$dbr = $this->dbProvider->getReplicaDatabase();
		$langs = array_unique( [
			$langCode,
			...$this->languageFallback->getAll( $langCode ),
		] );
		$wishesSelect = $dbr->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->table( 'communityrequests_wishes' )
			->join( 'page', null, [ 'cr_page = page_id' ] )
			->join( 'communityrequests_wishes_translations', null, 'crt_wish = cr_page' )
			->fields( self::WISH_FIELDS )
			->where( $dbr->makeList( [
				'crt_lang' => $langs,
				'crt_lang = cr_base_lang',
			], $dbr::LIST_OR ) )
			->orderBy( $orderBy, $direction );
		if ( $limit !== null ) {
			// Leave room for the fallback languages.
			$wishesSelect->limit( $limit * count( $langs ) );
		}

		$wishes = $this->getWishesFromLangFallbacks( $dbr, $wishesSelect->fetchResultSet(), $langCode );

		if ( $limit !== null ) {
			$wishes = array_slice( $wishes, 0, $limit );
		}

		return $wishes;
	}

	/**
	 * Parse wish data from the template invocation on a wish page.
	 *
	 * @param Wish $wish The wish to get data from.
	 * @return ?array<string, string> The parsed data from the wikitext, or null if not found.
	 *   This includes a 'baseRevId' key with the latest revision ID of the wish page.
	 */
	public function getDataFromWikitext( Wish $wish ): ?array {
		if ( !$wish->getPage()->getId() ) {
			return null;
		}
		$configTitle = $this->titleFormatter->getPrefixedDBkey(
			$this->titleParser->parseTitle( $this->config->getWishTemplatePage(), NS_TEMPLATE )
		);
		$revRecord = $this->revisionStore
			->getRevisionByPageId( $wish->getPage()->getId() );
		/** @var WikitextContent $content */
		$content = $revRecord->getMainContentRaw();
		'@phan-var WikitextContent $content';
		$wikitext = $content->getText();
		$args = ( new TemplateArgumentExtractor( $this->parserFactory, $this->titleParser ) )
			->getArgs(
				$this->titleParser->parseTitle( $this->config->getWishTemplatePage(), NS_TEMPLATE ),
				$wikitext
			);
		if ( $args !== null ) {
			// Include baseRevId
			$args[ 'baseRevId' ] = $revRecord->getId();
		}
		return $args;
	}

	/**
	 * Create a list of Wish objects from the given result set, grouping by page ID and using
	 * the first row with a matching language in the user's language and/or fallback chain,
	 * or finally the base language if no match is found.
	 *
	 * This method is also responsible for fetching associated projects and Phab task IDs.
	 *
	 * @param IReadableDatabase $dbr The database connection.
	 * @param IResultWrapper $resultWrapper The DB result wrapper.
	 * @param ?string $langCode The requested language code. Null to use the base language.
	 * @return array<Wish>
	 */
	private function getWishesFromLangFallbacks(
		IReadableDatabase $dbr,
		IResultWrapper $resultWrapper,
		?string $langCode = null
	): array {
		$fallbackLangs = $langCode === null ? [] : array_unique( [
			$langCode,
			...$this->languageFallback->getAll( $langCode ),
		] );

		$wishes = [];

		// Group the result set by wish page ID and language.
		$wishDataByPage = [];
		foreach ( $resultWrapper as $wishData ) {
			$wishDataByPage[ $wishData->cr_page ][ $wishData->crt_lang ] = $wishData;
		}

		// Fetch projects for all wishes in one go, and then the same for Phab tasks.
		$projectsByPage = $this->getProjectsForWishes( $dbr, array_keys( $wishDataByPage ) );
		$phabTasksByPage = $this->getPhabTasksForWishes( $dbr, array_keys( $wishDataByPage ) );

		foreach ( $wishDataByPage as $wishDataByLang ) {
			// All rows in $wishData have the same cr_base_lang
			$baseLang = reset( $wishDataByLang )->cr_base_lang;
			// This will be overridden if a user-preferred language is found.
			$row = $wishDataByLang[ $baseLang ];

			// Find the first row with a matching language.
			foreach ( $fallbackLangs as $lang ) {
				if ( isset( $wishDataByLang[ $lang ] ) ) {
					$row = $wishDataByLang[ $lang ];
					break;
				}
			}

			$wishes[] = new Wish(
				new PageIdentityValue(
					(int)$row->cr_page,
					(int)$row->page_namespace,
					$row->page_title,
					WikiAwareEntity::LOCAL
				),
				$row->crt_lang,
				$this->userFactory->newFromActorId( (int)$row->cr_actor ),
				[
					'type' => (int)$row->cr_type,
					'status' => (int)$row->cr_status,
					'focusAreaId' => (int)$row->cr_focus_area,
					'voteCount' => (int)$row->cr_vote_count,
					'created' => $row->cr_created,
					'updated' => $row->cr_updated,
					'title' => $row->crt_title,
					'projects' => $projectsByPage[ $row->cr_page ] ?? [],
					'otherProject' => $row->crt_other_project,
					'phabTasks' => $phabTasksByPage[ $row->cr_page ] ?? [],
					'baseLang' => $row->cr_base_lang,
				]
			);
		}

		return $wishes;
	}

	/**
	 * Get the projects associated with the given wishes.
	 *
	 * @param IReadableDatabase $dbr The database connection.
	 * @param array<int> $pageIds The page/wish IDs of the wishes.
	 * @return array<int> The IDs of the projects associated with the wishes, keyed by wish ID.
	 */
	private function getProjectsForWishes( IReadableDatabase $dbr, array $pageIds ): array {
		$projects = $dbr->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->table( 'communityrequests_projects' )
			->fields( [ 'crp_wish', 'crp_project' ] )
			->where( [ 'crp_wish' => $pageIds ] )
			->fetchResultSet();

		// Group by wish ID.
		$projectsByWish = [];
		foreach ( $projects as $project ) {
			$projectsByWish[ $project->crp_wish ][] = (int)$project->crp_project;
		}

		return $projectsByWish;
	}

	/**
	 * Get the Phabricator tasks associated with the given wishes.
	 *
	 * @param IReadableDatabase $dbr The database connection.
	 * @param array<int> $pageIds The page/wish IDs of the wishes.
	 * @return array<int> The IDs of the tasks associated with the wishes, keyed by wish ID.
	 */
	private function getPhabTasksForWishes( IReadableDatabase $dbr, array $pageIds ): array {
		$phabTasks = $dbr->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->table( 'communityrequests_phab_tasks' )
			->fields( [ 'crpt_wish', 'crpt_task_id' ] )
			->where( [ 'crpt_wish' => $pageIds ] )
			->fetchResultSet();

		// Group by wish ID.
		$phabTasksByWish = [];
		foreach ( $phabTasks as $task ) {
			$phabTasksByWish[ $task->crpt_wish ][] = (int)$task->crpt_task_id;
		}

		return $phabTasksByWish;
	}

	/**
	 * Delete a wish and all its associated data.
	 * Called from WishHookHandler::onPageDeleteComplete().
	 *
	 * @param Wish $wish
	 */
	public function delete( Wish $wish ): void {
		$dbw = $this->dbProvider->getPrimaryDatabase();
		$dbw->startAtomic( __METHOD__ );

		// First delete translations.
		$delTranslations = $dbw->newDeleteQueryBuilder()
			->deleteFrom( 'communityrequests_wishes_translations' )
			->where( [ 'crt_wish' => $wish->getPage()->getId() ] );

		// Delete only for the given language, if not the base language.
		if ( $wish->getLanguage() !== $wish->getBaseLanguage() ) {
			$delTranslations->andWhere( [ 'crt_lang' => $wish->getLanguage() ] );
		}

		$delTranslations->caller( __METHOD__ )
			->execute();

		// Delete everything else if we're dealing with the base language.
		if ( $wish->getLanguage() === $wish->getBaseLanguage() ) {
			foreach ( [
				'communityrequests_wishes' => 'cr_page',
				'communityrequests_projects' => 'crp_wish',
				'communityrequests_phab_tasks' => 'crpt_wish'
			] as $table => $foreignKey ) {
				$dbw->newDeleteQueryBuilder()
					->deleteFrom( $table )
					->where( [ $foreignKey => $wish->getPage()->getId() ] )
					->caller( __METHOD__ )
					->execute();
			}
		}

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Check if the given PageIdentity could be a wish page based on its title.
	 *
	 * @param PageIdentity|string $identity
	 * @return bool
	 * @throws InvalidArgumentException
	 */
	public function isWishPage( $identity ): bool {
		$pagePrefix = $this->titleParser->parseTitle( $this->config->getWishPagePrefix() );
		if ( is_string( $identity ) ) {
			$identity = $this->titleParser->parseTitle( $identity );
		} elseif ( !$identity instanceof PageIdentity ) {
			throw new InvalidArgumentException( 'Expected a PageIdentity or string.' );
		}

		return str_starts_with(
			$this->titleFormatter->getPrefixedDBkey( $identity ),
			$this->titleFormatter->getPrefixedDBkey( $pagePrefix )
		);
	}

	/**
	 * Get a new wish ID using the IdGenerator.
	 *
	 * @return int The new wish ID.
	 */
	public function getNewId(): int {
		return $this->idGenerator->getNewId( IdGenerator::TYPE_WISH );
	}
}
