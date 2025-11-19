<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\FocusArea;

use InvalidArgumentException;
use MediaWiki\DAO\WikiAwareEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Page\PageIdentityValue;
use Wikimedia\Rdbms\IReadableDatabase;

class FocusAreaStore extends AbstractWishlistStore {

	/** @inheritDoc */
	public function entityType(): string {
		return 'focus-area';
	}

	/**
	 * Get wiki page IDs from focus area wikitext values.
	 *
	 * @param string[] $focusAreas List of 'FAn' ID values and optionally 'unassigned'.
	 * @return mixed[] The page IDs, with null representing unassigned.
	 */
	public function getPageIdsFromWikitextValues( array $focusAreas ): array {
		$includeUnassigned = in_array( WishStore::FOCUS_AREA_UNASSIGNED, array_map( 'strtolower', $focusAreas ) );
		if ( $includeUnassigned && count( $focusAreas ) === 1 ) {
			// Only unassigned, return early.
			return [ null ];
		}
		// Get full page titles for all the given focus areas.
		$focusAreaPages = [];
		foreach ( $focusAreas as $faName ) {
			$faId = $this->getIdFromInput( $faName );
			if ( $faId ) {
				$focusAreaPage = $this->config->getFocusAreaPageRefFromWikitextVal( (string)$faId );
				if ( $focusAreaPage ) {
					$focusAreaPages[] = $focusAreaPage->getDBkey();
				}
			}
		}
		$faIds = [];
		if ( count( $focusAreaPages ) > 0 ) {
			// Then use these to find the page IDs.
			$prefixRef = $this->config->getFocusAreaPageRefFromWikitextVal( 'FA1' );
			$focusAreaPageIdsQuery = $this->dbProvider->getReplicaDatabase()
				->newSelectQueryBuilder()
				->caller( __METHOD__ )
				->table( 'page' )
				->fields( 'page_id' )
				->where( [
					'page_namespace' => $prefixRef->getNamespace(),
					'page_title' => $focusAreaPages
				] );
			$faIds = $focusAreaPageIdsQuery->fetchFieldValues();
		}
		if ( $includeUnassigned ) {
			$faIds[] = null;
		}
		return $faIds;
	}

	/**
	 * Get total wish counts per focus area.
	 *
	 * @param FocusArea|null $focusArea If given, only return the count for this focus area.
	 * @return int|int[] Wish count if given a $focusArea, or an associative array where
	 *   keys are focus area page IDs, values are unformatted integer counts of wishes.
	 */
	public function getWishCounts( ?FocusArea $focusArea = null ): int|array {
		$select = $this->dbProvider->getReplicaDatabase( 'virtual-communityrequests' )
			->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->table( self::tableName(), 'fa' )
			->leftJoin( WishStore::tableName(), 'w', 'fa.cr_page = w.cr_focus_area' )
			->fields( $focusArea ? 'COUNT(w.cr_page)' : [
				'faPageId' => 'fa.cr_page',
				'wishCount' => 'COUNT(w.cr_page)'
			] )
			->groupBy( 'fa.cr_page' );
		if ( $focusArea ) {
			$select->where( [ 'fa.cr_page' => $focusArea->getPage()->getId() ] );
			return (int)$select->fetchField();
		}
		$results = $select->fetchResultSet();
		$out = [];
		foreach ( $results as $res ) {
			$out[ $res->faPageId ] = (int)$res->wishCount;
		}
		return $out;
	}

	/**
	 * Get the titles of all focus areas keyed by their entity ID as wikitext values,
	 *  i.e. [ 'FA1' => 'The first focus area', ... ]
	 *
	 * @param string $lang
	 * @return string[]
	 */
	public function getTitlesByEntityWikitextVal( string $lang ): array {
		// Fetch focus area titles and wikitext values.
		/** @var FocusArea[] $focusAreas */
		$focusAreas = $this->getAll(
			$lang,
			self::titleField(),
			self::SORT_ASC,
			self::MAX_FOCUS_AREAS
		);
		$focusAreasData = [];
		foreach ( $focusAreas as $focusArea ) {
			$wikitextVal = $this->config->getEntityWikitextVal( $focusArea->getPage() );
			$focusAreasData[(string)$wikitextVal] = $focusArea->getTitle();
		}

		return $focusAreasData;
	}

	// Saving focus areas.

	/** @inheritDoc */
	public function save( AbstractWishlistEntity $entity ): void {
		if ( !$entity instanceof FocusArea ) {
			throw new InvalidArgumentException( '$entity must be a FocusArea instance.' );
		}
		if ( !$entity->getPage()->getId() ) {
			throw new InvalidArgumentException( 'Focus area page has not been added to the database yet!' );
		}
		if ( !$entity->getTitle() ) {
			throw new InvalidArgumentException( 'Focus areas must have a title!' );
		}
		$focusArea = $entity;

		$dbw = $this->dbProvider->getPrimaryDatabase( 'virtual-communityrequests' );
		$dbw->startAtomic( __METHOD__ );

		$data = [
			static::entityTypeField() => AbstractWishlistStore::ENTITY_TYPE_FOCUS_AREA,
			static::pageField() => $focusArea->getPage()->getId(),
		];
		$dataSet = [
			static::statusField() => $focusArea->getStatus(),
			static::createdField() => $dbw->timestamp( $focusArea->getCreated() ?: wfTimestampNow() ),
			static::updatedField() => $dbw->timestamp( $focusArea->getUpdated() ?: wfTimestampNow() ),
			static::baseLangField() => $focusArea->getBaseLang(),
		];

		// Set votes only if not null, otherwise leave unchanged.
		if ( $focusArea->getVoteCount() !== null ) {
			$dataSet[static::voteCountField()] = $focusArea->getVoteCount();
		}

		$dbw->newInsertQueryBuilder()
			->insert( static::tableName() )
			->rows( [ array_merge( $data, $dataSet ) ] )
			->set( $dataSet )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ static::pageField() ] )
			->caller( __METHOD__ )
			->execute();

		$this->logger->debug(
			__METHOD__ . ': Saved focus area {0} with data {1}',
			[ $focusArea->getPage()->__toString(), json_encode( array_merge( $data, $dataSet ) ) ]
		);

		$this->saveTranslations( $focusArea, $dbw );

		$dbw->endAtomic( __METHOD__ );
	}

	/** @inheritDoc */
	protected function getEntitiesFromDbResult(
		IReadableDatabase $dbr,
		array $rows,
		array $entityDataByPage
	): array {
		$focusAreas = [];
		foreach ( $rows as $row ) {
			$focusAreas[] = new FocusArea(
				new PageIdentityValue(
					(int)$row->{static::pageField()},
					(int)$row->page_namespace,
					$row->page_title,
					WikiAwareEntity::LOCAL
				),
				$row->{static::translationLangField()},
				[
					FocusArea::PARAM_STATUS => (int)$row->{static::statusField()},
					FocusArea::PARAM_TITLE => $row->{static::titleField()},
					FocusArea::PARAM_VOTE_COUNT => (int)$row->{static::voteCountField()},
					FocusArea::PARAM_CREATED => $row->{static::createdField()},
					FocusArea::PARAM_UPDATED => $row->{static::updatedField()},
					FocusArea::PARAM_BASE_LANG => $row->{static::baseLangField()},
					// "Virtual" fields that only exist when querying for wikitext.
					FocusArea::PARAM_DESCRIPTION => $row->crfat_description ?? '',
					FocusArea::PARAM_SHORT_DESCRIPTION => $row->crfat_short_description ?? '',
					FocusArea::PARAM_OWNERS => $row->crfat_owners ?? '',
					FocusArea::PARAM_VOLUNTEERS => $row->crfat_volunteers ?? '',
				]
			);
		}

		return $focusAreas;
	}

	/** @inheritDoc */
	public function getNewId(): int {
		return $this->idGenerator->getNewId( IdGenerator::TYPE_FOCUS_AREA );
	}

	public function getMappedFields(): array {
		return [
			FocusArea::PARAM_TITLE => static::titleField(),
			FocusArea::PARAM_DESCRIPTION => 'crfat_description',
			FocusArea::PARAM_SHORT_DESCRIPTION => 'crfat_short_description',
			FocusArea::PARAM_OWNERS => 'crfat_owners',
			FocusArea::PARAM_VOLUNTEERS => 'crfat_volunteers',
		];
	}

	/** @inheritDoc */
	public function getExtTranslateFields(): array {
		return [
			FocusArea::PARAM_TITLE,
			FocusArea::PARAM_DESCRIPTION,
			FocusArea::PARAM_SHORT_DESCRIPTION,
			FocusArea::PARAM_OWNERS,
			FocusArea::PARAM_VOLUNTEERS,
		];
	}

	/** @inheritDoc */
	public function getWikitextFields(): array {
		return [
			FocusArea::PARAM_DESCRIPTION,
			FocusArea::PARAM_SHORT_DESCRIPTION,
			FocusArea::PARAM_OWNERS,
			FocusArea::PARAM_VOLUNTEERS,
		];
	}

	/** @inheritDoc */
	public function getParams(): array {
		return FocusArea::PARAMS;
	}

	/** @inheritDoc */
	public function getArrayParams(): array {
		return [];
	}

	/** @inheritDoc */
	public function getPagePrefix(): string {
		return $this->config->getFocusAreaPagePrefix();
	}
}
