<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\FocusArea;

use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleParser;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;

class FocusAreaStore {

	public const FOCUS_AREA_FIELDS = [
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

	private IConnectionProvider $connectionProvider;
	private TitleParser $titleParser;
	private TitleFormatter $titleFormatter;
	private string $focusAreaPagePrefix;

	/**
	 * @param IConnectionProvider $connectionProvider
	 * @param WishlistConfig $config
	 * @param TitleParser $titleParser
	 * @param TitleFormatter $titleFormatter
	 */
	public function __construct(
		IConnectionProvider $connectionProvider,
		WishlistConfig $config,
		TitleParser $titleParser,
		TitleFormatter $titleFormatter
	) {
		$this->connectionProvider = $connectionProvider;
		$this->focusAreaPagePrefix = $config->getFocusAreaPagePrefix();
		$this->titleParser = $titleParser;
		$this->titleFormatter = $titleFormatter;
	}

	/**
	 * Save a focus area and translation to the database.
	 *
	 * @param FocusArea $focusArea
	 */
	public function save( FocusArea $focusArea ): void {
		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$dbw->startAtomic( __METHOD__ );

		$data = [
			'crfa_page' => $focusArea->getPage()->getId(),
			'crfa_base_lang' => $focusArea->getBaseLanguage(),
			'crfa_created' => $dbw->timestamp( $focusArea->getCreated() ?: wfTimestampNow() ),
		];
		$dataSet = [
			'crfa_updated' => $dbw->timestamp( $focusArea->getUpdated() ?: wfTimestampNow() ),
			'crfa_status' => $focusArea->getStatus(),
		];

		$dbw->newInsertQueryBuilder()
			->insert( 'communityrequests_focus_areas' )
			->rows( [ array_merge( $data, $dataSet ) ] )
			->set( $dataSet )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'crfa_page' ] )
			->caller( __METHOD__ )
			->execute();

		$this->saveTranslation( $focusArea, $dbw );

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Save a translation for a focus area to the database.
	 *
	 * @param FocusArea $focusArea
	 * @param IDatabase $dbw
	 */
	private function saveTranslation( FocusArea $focusArea, IDatabase $dbw ): void {
		$data = [
			'crfat_focus_area' => $focusArea->getPage()->getId(),
			'crfat_lang' => $focusArea->getLanguage()
		];

		$dataSet = [
			'crfat_title' => $focusArea->getTitle(),
			'crfat_short_description' => $focusArea->getShortDescription()
		];

		$dbw->newInsertQueryBuilder()
			->insert( 'communityrequests_focus_areas_translations' )
			->rows( [ array_merge( $data, $dataSet ) ] )
			->set( $dataSet )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'crfat_focus_area', 'crfat_lang' ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Get a focus area by its page identity and language.
	 *
	 * @param PageIdentity $page
	 * @param string $language
	 * @return FocusArea|null
	 */
	public function getFocusArea( PageIdentity $page, string $language ): ?FocusArea {
		$dbr = $this->connectionProvider->getReplicaDatabase();

		$focusArea = $dbr->newSelectQueryBuilder()
			->fields( self::FOCUS_AREA_FIELDS )
			->table( 'communityrequests_focus_areas' )
			->join( 'communityrequests_focus_areas_translations', null, [
				'crfa_page = crfat_focus_area',
			] )
			->where( [
				'crfa_page' => $page->getId(),
				'crfat_lang' => $language,
			] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$focusArea ) {
			return null;
		}

		return new FocusArea(
			$page,
			$language,
			[
				'baseLang' => $focusArea->crfa_base_lang,
				'shortDescription' => $focusArea->crfat_short_description,
				'title' => $focusArea->crfat_title,
				'created' => $focusArea->crfa_created,
				'updated' => $focusArea->crfa_updated,
				'status' => $focusArea->crfa_status
			]
		);
	}

	/**
	 * Check if a page is a focus area page based on the configured prefix.
	 *
	 * @param PageIdentity $identity
	 * @return bool
	 */
	public function isFocusAreaPage( PageIdentity $identity ): bool {
		$pagePrefix = $this->titleParser->parseTitle( $this->focusAreaPagePrefix );

		return str_starts_with(
			$this->titleFormatter->getPrefixedDBkey( $identity ),
			$this->titleFormatter->getPrefixedDBkey( $pagePrefix )
		);
	}
}
