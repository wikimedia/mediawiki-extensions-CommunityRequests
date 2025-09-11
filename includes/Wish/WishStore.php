<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use InvalidArgumentException;
use MediaWiki\DAO\WikiAwareEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageParser;
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
use Wikimedia\Rdbms\SelectQueryBuilder;

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
		protected ?TranslatablePageParser $translatablePageParser,
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
			$translatablePageParser,
		);
	}

	/** @inheritDoc */
	public function entityType(): string {
		return 'wish';
	}

	// Schema

	/** @inheritDoc */
	public static function fields(): array {
		return array_merge( parent::fields(), [
			static::wishTypeField(),
			static::focusAreaField(),
		] );
	}

	/**
	 * The field name for the wish type.
	 *
	 * @return string
	 */
	public static function wishTypeField(): string {
		return 'cr_wish_type';
	}

	/**
	 * The field name for the focus area page ID.
	 *
	 * @return string
	 */
	public static function focusAreaField(): string {
		return 'cr_focus_area';
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

		$dbw = $this->dbProvider->getPrimaryDatabase( 'virtual-communityrequests' );
		$dbw->startAtomic( __METHOD__ );

		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Proposer is checked and not null
		$proposer = $wish->getProposer() ? $this->actorNormalization->findActorId( $wish->getProposer(), $dbw ) : null;
		$created = $wish->getCreated();

		if ( !$proposer || !$created ) {
			// Fetch proposer and creation date from the wishes table.
			$proposerCreated = $dbw->newSelectQueryBuilder()
				->caller( __METHOD__ )
				->from( self::tableName() )
				->fields( [ static::actorField(), self::createdField() ] )
				->where( [ static::pageField() => $wish->getPage()->getId() ] )
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
			throw new InvalidArgumentException( 'Wishes must have a created timestamp!' );
		}

		$data = [
			static::entityTypeField() => AbstractWishlistStore::ENTITY_TYPE_WISH,
			static::pageField() => $wish->getPage()->getId(),
			static::baseLangField() => $wish->getBaseLang(),
		];
		$dataSet = [
			static::actorField() => $proposer,
			static::wishTypeField() => $wish->getType(),
			static::statusField() => $wish->getStatus(),
			static::focusAreaField() => $wish->getFocusAreaPage()?->getId() ?: null,
			static::createdField() => $dbw->timestamp( $created ),
			static::updatedField() => $dbw->timestamp( $wish->getUpdated() ?: wfTimestampNow() ),
		];

		// Set votes only if not null, otherwise leave unchanged.
		if ( $wish->getVoteCount() !== null ) {
			$dataSet[static::voteCountField()] = $wish->getVoteCount();
		}

		$dbw->newInsertQueryBuilder()
			->insert( self::tableName() )
			->rows( [ array_merge( $data, $dataSet ) ] )
			->set( $dataSet )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ static::pageField() ] )
			->caller( __METHOD__ )
			->execute();

		$this->logger->debug(
			__METHOD__ . ': Saved wish {0} with data {1}',
			[ $wish->getPage()->__toString(), $dataSet ]
		);

		$this->saveTranslations( $wish, $dbw );
		$this->saveTags( $wish, $dbw );

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Save the tags associated with a wish.
	 *
	 * @param Wish $wish The wish to save.
	 * @param IDatabase $dbw The database connection.
	 */
	private function saveTags( Wish $wish, IDatabase $dbw ): void {
		$queryMetadata = [
			[
				'table' => 'communityrequests_tags',
				'key' => 'crtg_tag',
				'foreignKey' => 'crtg_entity',
				'wishMethod' => 'getTags',
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
	protected function applyFilters(
		IReadableDatabase $dbr,
		SelectQueryBuilder $select,
		array $filters
	): SelectQueryBuilder {
		$select = parent::applyFilters( $dbr, $select, $filters );

		if ( isset( $filters[Wish::PARAM_TAGS] ) ) {
			$tagIds = [];
			foreach ( $this->config->getNavigationTags() as $tagName => $tagInfo ) {
				if ( in_array( $tagName, $filters[Wish::PARAM_TAGS] ) ) {
					$tagIds[] = $tagInfo['id'];
				}
			}
			// Probably unnecessary, because the API only allows valid tag names through.
			if ( count( $tagIds ) > 0 ) {
				// Select wishes with any of the given tags.
				$select->join( self::tagsTableName(), null, 'crtg_entity = cr_page' )
					->andWhere( [ 'crtg_tag' => $tagIds ] );
			}
		}

		// The focus area page IDs have already been fetched in ApiQueryWishes
		// and are passed here as page IDs.
		if ( isset( $filters['focus_area_page_ids'] ) && $filters['focus_area_page_ids'] ) {
			$select->andWhere( [ 'cr_focus_area' => $filters['focus_area_page_ids'] ] );
		}

		return $select;
	}

	/** @inheritDoc */
	protected function getEntitiesFromDbResult(
		IReadableDatabase $dbr,
		array $rows,
		array $entityDataByPage,
	): array {
		// Fetch tags for all wishes in one go, and then the same for Phab tasks.
		$tagsByPage = $this->getTagsForWishes( $dbr, array_keys( $entityDataByPage ) );

		$wishes = [];
		foreach ( $rows as $row ) {
			$wishes[] = new Wish(
				new PageIdentityValue(
					(int)$row->{static::pageField()},
					(int)$row->page_namespace,
					$row->page_title,
					WikiAwareEntity::LOCAL
				),
				$row->{static::translationLangField()},
				$this->userFactory->newFromActorId( (int)$row->{static::actorField()} ),
				[
					Wish::PARAM_TYPE => (int)$row->{static::wishTypeField()},
					Wish::PARAM_STATUS => (int)$row->{static::statusField()},
					Wish::PARAM_TITLE => $row->{static::titleField()},
					Wish::PARAM_FOCUS_AREA => Title::newFromID( (int)$row->{static::focusAreaField()} ),
					Wish::PARAM_TAGS => $tagsByPage[$row->{static::pageField()}] ?? [],
					Wish::PARAM_VOTE_COUNT => (int)$row->{static::voteCountField()},
					Wish::PARAM_CREATED => $row->{static::createdField()},
					Wish::PARAM_UPDATED => $row->{static::updatedField()},
					Wish::PARAM_BASE_LANG => $row->{static::baseLangField()},
					// "Virtual" fields that only exist when querying for wikitext.
					Wish::PARAM_DESCRIPTION => $row->crt_description ?? null,
					Wish::PARAM_AUDIENCE => $row->crt_audience ?? null,
					Wish::PARAM_PHAB_TASKS => Wish::getPhabTasksFromCsv( $row->crt_tasks ?? '' ),
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
			->table( static::tagsTableName() )
			->fields( [ 'crtg_entity', 'crtg_tag' ] )
			->where( [ 'crtg_entity' => $pageIds ] )
			->fetchResultSet();

		// Group by wish ID.
		$tagsByWish = [];
		foreach ( $tags as $tag ) {
			$tagsByWish[$tag->crtg_entity][] = (int)$tag->crtg_tag;
		}

		return $tagsByWish;
	}

	/** @inheritDoc */
	public function delete( AbstractWishlistEntity $entity, array $assocData = [] ): IDatabase {
		return parent::delete( $entity, [
			static::tagsTableName() => 'crtg_entity',
		] );
	}

	/** @inheritDoc */
	public function getNewId(): int {
		return $this->idGenerator->getNewId( IdGenerator::TYPE_WISH );
	}

	/** @inheritDoc */
	public function getExtTranslateFields(): array {
		return [
			Wish::PARAM_TITLE => self::titleField(),
			// Wikitext fields.
			Wish::PARAM_DESCRIPTION => 'crt_description',
			Wish::PARAM_AUDIENCE => 'crt_audience',
			// We are using this field as a virtual field even though is it not translatable
			Wish::PARAM_PHAB_TASKS => 'crt_tasks',
		];
	}

	/** @inheritDoc */
	public function getWikitextFields(): array {
		return [
			Wish::PARAM_DESCRIPTION,
			Wish::PARAM_AUDIENCE,
			Wish::PARAM_PHAB_TASKS
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
