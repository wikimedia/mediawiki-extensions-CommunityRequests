<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use InvalidArgumentException;
use MediaWiki\DAO\WikiAwareEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Languages\LanguageFallback;
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

/**
 * WishStore is responsible for all database operations related to wishes.
 *
 * @note "Wish" is the user-facing term, while "request" is used in storage.
 */
class WishStore extends AbstractWishlistStore {

	public const AUDIENCE_MAX_CHARS = 300;

	public function __construct(
		private readonly ActorNormalization $actorNormalization,
		protected IConnectionProvider $dbProvider,
		private readonly UserFactory $userFactory,
		protected LanguageFallback $languageFallback,
		protected RevisionStore $revisionStore,
		protected ParserFactory $parserFactory,
		protected TitleParser $titleParser,
		protected TitleFormatter $titleFormatter,
		protected IdGenerator $idGenerator,
		protected WishlistConfig $config,
	) {
		parent::__construct(
			$dbProvider,
			$languageFallback,
			$revisionStore,
			$parserFactory,
			$titleParser,
			$titleFormatter,
			$idGenerator,
			$config,
		);
	}

	// Schema

	/** @inheritDoc */
	protected static function tableName(): string {
		return 'communityrequests_wishes';
	}

	/** @inheritDoc */
	public static function fields(): array {
		return [
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
	}

	/** @inheritDoc */
	public static function pageField(): string {
		return 'cr_page';
	}

	/** @inheritDoc */
	public static function createdField(): string {
		return 'cr_created';
	}

	/** @inheritDoc */
	public static function updatedField(): string {
		return 'cr_updated';
	}

	/** @inheritDoc */
	public static function voteCountField(): string {
		return 'cr_vote_count';
	}

	/** @inheritDoc */
	protected static function baseLangField(): string {
		return 'cr_base_lang';
	}

	/** @inheritDoc */
	public static function titleField(): string {
		return 'crt_title';
	}

	/** @inheritDoc */
	protected static function translationsTableName(): string {
		return 'communityrequests_wishes_translations';
	}

	/** @inheritDoc */
	protected static function translationForeignKey(): string {
		return 'crt_wish';
	}

	/** @inheritDoc */
	protected static function translationLangField(): string {
		return 'crt_lang';
	}

	// Saving wishes.

	/** @inheritDoc */
	public function save( AbstractWishlistEntity $entity ): void {
		if ( !$entity instanceof Wish ) {
			throw new InvalidArgumentException( '$entity must be a Wish instance.' );
		}
		if ( !$entity->getPage()->getId() ) {
			throw new InvalidArgumentException( 'Wish page has not been added to the database yet!' );
		}
		$wish = $entity;

		$dbw = $this->dbProvider->getPrimaryDatabase();
		$dbw->startAtomic( __METHOD__ );

		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Proposer is checked and not null
		$proposer = $wish->getProposer() ? $this->actorNormalization->findActorId( $wish->getProposer(), $dbw ) : null;
		$created = $wish->getCreated();

		if ( !$proposer || !$created ) {
			// Fetch proposer and creation date from the wishes table.
			$proposerCreated = $dbw->newSelectQueryBuilder()
				->caller( __METHOD__ )
				->from( self::tableName() )
				->fields( [ 'cr_actor', self::createdField() ] )
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
			'cr_base_lang' => $wish->getBaseLang(),
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
			->insert( self::tableName() )
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

	/** @inheritDoc */
	protected function saveTranslations(
		AbstractWishlistEntity $entity,
		IDatabase $dbw,
		array $dataSet = [],
	): void {
		if ( !$entity instanceof Wish ) {
			throw new InvalidArgumentException( '$entity must be a Wish instance.' );
		}
		parent::saveTranslations( $entity, $dbw, [
			'crt_other_project' => $entity->getOtherProject() ?: null,
		] );
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

	/** @inheritDoc */
	protected function getEntitiesFromLangFallbacks(
		IReadableDatabase $dbr,
		IResultWrapper $resultWrapper,
		?string $lang = null
	): array {
		[ $rows, $wishesByPage ] = parent::getEntitiesFromLangFallbacksInternal( $resultWrapper, $lang );

		// Fetch projects for all wishes in one go, and then the same for Phab tasks.
		$projectsByPage = $this->getProjectsForWishes( $dbr, array_keys( $wishesByPage ) );
		$phabTasksByPage = $this->getPhabTasksForWishes( $dbr, array_keys( $wishesByPage ) );

		$wishes = [];
		foreach ( $rows as $row ) {
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
					'title' => $row->crt_title,
					'projects' => $projectsByPage[ $row->cr_page ] ?? [],
					'otherProject' => $row->crt_other_project,
					'phabTasks' => $phabTasksByPage[ $row->cr_page ] ?? [],
					'voteCount' => (int)$row->cr_vote_count,
					'created' => $row->cr_created,
					'updated' => $row->cr_updated,
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
		if ( !count( $pageIds ) ) {
			return [];
		}
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
		if ( !count( $pageIds ) ) {
			return [];
		}
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

	/** @inheritDoc */
	public function delete( AbstractWishlistEntity $entity, array $assocData = [] ): IDatabase {
		return parent::delete( $entity, [
			'communityrequests_projects' => 'crp_wish',
			'communityrequests_phab_tasks' => 'crpt_wish'
		] );
	}

	/** @inheritDoc */
	public function getNewId(): int {
		return $this->idGenerator->getNewId( IdGenerator::TYPE_WISH );
	}

	/** @inheritDoc */
	public function getWikitextFields(): array {
		return [
			Wish::TAG_ATTR_DESCRIPTION,
			Wish::TAG_ATTR_AUDIENCE,
		];
	}

	/** @inheritDoc */
	public function getTemplateParams(): array {
		return $this->config->getWishTemplateParams();
	}

	/** @inheritDoc */
	public function getPagePrefix(): string {
		return $this->config->getWishPagePrefix();
	}

	public function entityType(): string {
		return 'wish';
	}
}
