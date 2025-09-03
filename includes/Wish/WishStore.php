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
use MediaWiki\Page\PageStore;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleParser;
use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserFactory;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

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
		protected PageStore $pageStore,
		protected IdGenerator $idGenerator,
		protected WishlistConfig $config,
		protected LoggerInterface $logger,
	) {
		parent::__construct(
			$dbProvider,
			$languageFallback,
			$revisionStore,
			$parserFactory,
			$titleParser,
			$titleFormatter,
			$pageStore,
			$idGenerator,
			$config,
			$logger,
		);
	}

	/** @inheritDoc */
	public function entityType(): string {
		return 'wish';
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
		if ( !$entity->getTitle() ) {
			throw new InvalidArgumentException( 'Wishes must have a title!' );
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
			'cr_focus_area' => $wish->getFocusAreaPage()?->getId() ?: null,
			'cr_created' => $dbw->timestamp( $created ),
			'cr_updated' => $dbw->timestamp( $wish->getUpdated() ?: wfTimestampNow() ),
		];

		// Set votes only if not null, otherwise leave unchanged.
		if ( $wish->getVoteCount() !== null ) {
			$dataSet['cr_vote_count'] = $wish->getVoteCount();
		}

		$dbw->newInsertQueryBuilder()
			->insert( self::tableName() )
			->rows( [ array_merge( $data, $dataSet ) ] )
			->set( $dataSet )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'cr_page' ] )
			->caller( __METHOD__ )
			->execute();

		$this->logger->debug(
			__METHOD__ . ': Saved wish {0} with data {1}',
			[ $wish->getPage()->__toString(), $dataSet ]
		);

		$this->saveTranslations( $wish, $dbw );
		$this->saveTagsAndPhabTasks( $wish, $dbw );

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Save the tags and Phabricator tasks associated with a wish.
	 *
	 * @param Wish $wish The wish to save.
	 * @param IDatabase $dbw The database connection.
	 */
	private function saveTagsAndPhabTasks( Wish $wish, IDatabase $dbw ): void {
		$queryMetadata = [
			[
				'table' => 'communityrequests_tags',
				'key' => 'crtg_tag',
				'foreignKey' => 'crtg_wish',
				'wishMethod' => 'getTags',
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
	protected function getEntitiesFromDbResult(
		IReadableDatabase $dbr,
		array $rows,
		array $entityDataByPage,
	): array {
		// Fetch tags for all wishes in one go, and then the same for Phab tasks.
		$tagsByPage = $this->getTagsForWishes( $dbr, array_keys( $entityDataByPage ) );
		$phabTasksByPage = $this->getPhabTasksForWishes( $dbr, array_keys( $entityDataByPage ) );

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
					Wish::PARAM_TYPE => (int)$row->cr_type,
					Wish::PARAM_STATUS => (int)$row->cr_status,
					Wish::PARAM_TITLE => $row->crt_title,
					// TODO: refactor to fetch page ID in the main query.
					Wish::PARAM_FOCUS_AREA => Title::newFromID( (int)$row->cr_focus_area ),
					Wish::PARAM_TAGS => $tagsByPage[$row->cr_page] ?? [],
					Wish::PARAM_PHAB_TASKS => $phabTasksByPage[$row->cr_page] ?? [],
					Wish::PARAM_VOTE_COUNT => (int)$row->cr_vote_count,
					Wish::PARAM_CREATED => $row->cr_created,
					Wish::PARAM_UPDATED => $row->cr_updated,
					Wish::PARAM_BASE_LANG => $row->cr_base_lang,
					// "Virtual" fields that only exist when querying for wikitext.
					Wish::PARAM_DESCRIPTION => $row->crt_description ?? null,
					Wish::PARAM_AUDIENCE => $row->crt_audience ?? null,
				]
			);
		}

		return $wishes;
	}

	/**
	 * Get the tags associated with the given wishes.
	 *
	 * @param IReadableDatabase $dbr The database connection.
	 * @param array<int> $pageIds The page/wish IDs of the wishes.
	 * @return array<int> The IDs of the tags associated with the wishes, keyed by wish ID.
	 */
	private function getTagsForWishes( IReadableDatabase $dbr, array $pageIds ): array {
		if ( !count( $pageIds ) ) {
			return [];
		}
		$tags = $dbr->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->table( 'communityrequests_tags' )
			->fields( [ 'crtg_wish', 'crtg_tag' ] )
			->where( [ 'crtg_wish' => $pageIds ] )
			->fetchResultSet();

		// Group by wish ID.
		$tagsByWish = [];
		foreach ( $tags as $tag ) {
			$tagsByWish[$tag->crtg_wish][] = (int)$tag->crtg_tag;
		}

		return $tagsByWish;
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
			$phabTasksByWish[$task->crpt_wish][] = (int)$task->crpt_task_id;
		}

		return $phabTasksByWish;
	}

	/** @inheritDoc */
	public function delete( AbstractWishlistEntity $entity, array $assocData = [] ): IDatabase {
		return parent::delete( $entity, [
			'communityrequests_tags' => 'crtg_wish',
			'communityrequests_phab_tasks' => 'crpt_wish'
		] );
	}

	/** @inheritDoc */
	public function getNewId(): int {
		return $this->idGenerator->getNewId( IdGenerator::TYPE_WISH );
	}

	/** @inheritDoc */
	public function getExtTranslateFields(): array {
		return [
			Wish::PARAM_TITLE => 'crt_title',
			Wish::PARAM_DESCRIPTION => 'crt_description',
			Wish::PARAM_AUDIENCE => 'crt_audience',
		];
	}

	/** @inheritDoc */
	public function getWikitextFields(): array {
		return [
			Wish::PARAM_DESCRIPTION,
			Wish::PARAM_AUDIENCE,
		];
	}

	/** @inheritDoc */
	public function getParams(): array {
		return Wish::PARAMS;
	}

	/** @inheritDoc */
	public function getArrayParams(): array {
		return [
			Wish::PARAM_TAGS,
			Wish::PARAM_PHAB_TASKS,
		];
	}

	/** @inheritDoc */
	public function getPagePrefix(): string {
		return $this->config->getWishPagePrefix();
	}
}
