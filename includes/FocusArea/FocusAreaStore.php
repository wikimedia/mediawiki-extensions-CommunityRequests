<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\FocusArea;

use InvalidArgumentException;
use MediaWiki\DAO\WikiAwareEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\IdGenerator\IdGenerator;
use MediaWiki\Page\PageIdentityValue;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

class FocusAreaStore extends AbstractWishlistStore {

	/** @inheritDoc */
	public function entityType(): string {
		return 'focus-area';
	}

	// Schema

	/** @inheritDoc */
	public static function tableName(): string {
		return 'communityrequests_focus_areas';
	}

	/** @inheritDoc */
	public static function fields(): array {
		return [
			'page_namespace',
			'page_title',
			'crfa_page',
			'crfa_base_lang',
			'crfa_status',
			'crfa_vote_count',
			'crfa_created',
			'crfa_updated',
			'crfat_title',
			'crfat_short_description',
			'crfat_title',
			'crfat_lang',
		];
	}

	/** @inheritDoc */
	protected static function pageField(): string {
		return 'crfa_page';
	}

	/** @inheritDoc */
	public static function createdField(): string {
		return 'crfa_created';
	}

	/** @inheritDoc */
	public static function updatedField(): string {
		return 'crfa_updated';
	}

	/** @inheritDoc */
	public static function voteCountField(): string {
		return 'crfa_vote_count';
	}

	/** @inheritDoc */
	public static function baseLangField(): string {
		return 'crfa_base_lang';
	}

	/** @inheritDoc */
	public static function titleField(): string {
		return 'crfat_title';
	}

	/** @inheritDoc */
	protected static function translationsTableName(): string {
		return 'communityrequests_focus_areas_translations';
	}

	/** @inheritDoc */
	protected static function translationForeignKey(): string {
		return 'crfat_focus_area';
	}

	/** @inheritDoc */
	protected static function translationLangField(): string {
		return 'crfat_lang';
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
		$focusArea = $entity;

		$dbw = $this->dbProvider->getPrimaryDatabase();
		$dbw->startAtomic( __METHOD__ );

		$data = [
			'crfa_page' => $focusArea->getPage()->getId(),
			'crfa_base_lang' => $focusArea->getBaseLang(),
			'crfa_created' => $dbw->timestamp( $focusArea->getCreated() ?: wfTimestampNow() ),
		];
		$dataSet = [
			'crfa_updated' => $dbw->timestamp( $focusArea->getUpdated() ?: wfTimestampNow() ),
			'crfa_status' => $focusArea->getStatus(),
		];

		// Set votes only if not null, otherwise leave unchanged.
		if ( $focusArea->getVoteCount() !== null ) {
			$dataSet['crfa_vote_count'] = $focusArea->getVoteCount();
		}

		$dbw->newInsertQueryBuilder()
			->insert( 'communityrequests_focus_areas' )
			->rows( [ array_merge( $data, $dataSet ) ] )
			->set( $dataSet )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'crfa_page' ] )
			->caller( __METHOD__ )
			->execute();

		$this->saveTranslations( $focusArea, $dbw );

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Save a translation for a focus area to the database.
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
		if ( !$entity instanceof FocusArea ) {
			throw new InvalidArgumentException( '$entity must be a FocusArea instance.' );
		}
		parent::saveTranslations( $entity, $dbw, [
			'crfat_short_description' => $entity->getShortDescription(),
		] );
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
					(int)$row->crfa_page,
					(int)$row->page_namespace,
					$row->page_title,
					WikiAwareEntity::LOCAL
				),
				$row->crfat_lang,
				[
					FocusArea::PARAM_STATUS => (int)$row->crfa_status,
					FocusArea::PARAM_TITLE => $row->crfat_title,
					FocusArea::PARAM_SHORT_DESCRIPTION => $row->crfat_short_description,
					FocusArea::PARAM_VOTE_COUNT => (int)$row->crfa_vote_count,
					FocusArea::PARAM_CREATED => $row->crfa_created,
					FocusArea::PARAM_UPDATED => $row->crfa_updated,
					FocusArea::PARAM_BASE_LANG => $row->crfa_base_lang,
					// "Virtual" fields that only exist when querying for wikitext.
					FocusArea::PARAM_DESCRIPTION => $row->crfat_description ?? '',
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

	/** @inheritDoc */
	public function getExtTranslateFields(): array {
		return [
			FocusArea::PARAM_TITLE => 'crfat_title',
			FocusArea::PARAM_DESCRIPTION => 'crfat_description',
			FocusArea::PARAM_SHORT_DESCRIPTION => 'crfat_short_description',
			FocusArea::PARAM_OWNERS => 'crfat_owners',
			FocusArea::PARAM_VOLUNTEERS => 'crfat_volunteers',
		];
	}

	/** @inheritDoc */
	public function getWikitextFields(): array {
		return [
			FocusArea::PARAM_DESCRIPTION,
			FocusArea::PARAM_OWNERS,
			FocusArea::PARAM_VOLUNTEERS,
		];
	}

	/** @inheritDoc */
	public function getParams(): array {
		return FocusArea::PARAMS;
	}

	/** @inheritDoc */
	public function getPagePrefix(): string {
		return $this->config->getFocusAreaPagePrefix();
	}
}
