<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Maintenance;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Maintenance script to create a test wishes and focus areas.
 * This is for development purposes only.
 */
class CreateTestWishlist extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 100 );
		$this->addDescription( 'Create test wishes and focus areas.' );
		$this->addOption( 'num-wishes', 'Number of test wishes to create', false, true, 'W' );
		$this->addOption( 'num-focus-areas', 'Number of test focus areas to create', false, true, 'FA' );
		$this->addOption(
			'lang',
			'Language code for the test wishes. Omit to leave random.',
			false,
			true,
			'L'
		);
		$this->requireExtension( 'CommunityRequests' );
	}

	/** @inheritDoc */
	public function execute() {
		$numWishes = (int)$this->getOption( 'num-wishes', 50 );
		$numFocusAreas = (int)$this->getOption( 'num-focus-areas', 5 );

		$services = $this->getServiceContainer();
		$pageUpdaterFactory = $services->getPageUpdaterFactory();
		/** @var WishlistConfig $config */
		$config = $services->get( 'CommunityRequests.WishlistConfig' );
		/** @var WishStore $wishStore */
		$wishStore = $services->get( 'CommunityRequests.WishStore' );
		/** @var FocusAreaStore $focusAreaStore */
		$focusAreaStore = $services->get( 'CommunityRequests.FocusAreaStore' );

		$user = User::newSystemUser( 'Maintenance script', [ 'steal' => true ] );

		$focusAreaIds = [];

		for ( $i = 1; $i <= $numFocusAreas; $i++ ) {
			$title = $config->getFocusAreaPagePrefix() . $focusAreaStore->getNewId();
			$focusArea = FocusArea::newFromWikitextParams(
				Title::newFromText( $title ),
				'en',
				[
					FocusArea::PARAM_STATUS => array_rand( $config->getStatuses() ),
					FocusArea::PARAM_TITLE => $this->getRandomTextBlob( 1, 1, 5 ),
					FocusArea::PARAM_DESCRIPTION => $this->getRandomTextBlob(),
					FocusArea::PARAM_SHORT_DESCRIPTION => $this->getRandomTextBlob( 1, 3 ),
					FocusArea::PARAM_OWNERS => $this->getRandomTextBlob( 1, 1, 5, true ),
					FocusArea::PARAM_VOLUNTEERS => $this->getRandomTextBlob( 1, 1, 5, true ),
					FocusArea::PARAM_CREATED => $this->getRandomTimestamp(),
					FocusArea::PARAM_BASE_LANG => 'en',
				],
				$config
			);
			$revRecord = $pageUpdaterFactory->newPageUpdater( $focusArea->getPage(), $user )
				->setContent( SlotRecord::MAIN, $focusArea->toWikitext( $config ) )
				->saveRevision( 'Creating test focus area' );
			$focusAreaIds[] = $config->getEntityWikitextVal( $revRecord->getPage() );

			$this->output( "Created focus area: $title\n" );
		}

		$langs = $this->hasOption( 'lang' ) ?
			[ $this->getOption( 'lang' ) ] :
			[ 'en', 'de', 'bs', 'hr', 'fr' ];
		for ( $i = 1; $i <= $numWishes; $i++ ) {
			$title = $config->getWishPagePrefix() . $wishStore->getNewId();
			$projects = array_rand( $config->getProjects(), rand( 1, 3 ) );
			$lang = $langs[array_rand( $langs )];
			$wish = Wish::newFromWikitextParams(
				Title::newFromText( $title ),
				$lang,
				[
					Wish::PARAM_TYPE => array_rand( $config->getWishTypes() ),
					Wish::PARAM_STATUS => array_rand( $config->getStatuses() ),
					Wish::PARAM_TITLE => $this->getRandomTextBlob( 1, 1, 5 ) . " ($lang)",
					Wish::PARAM_DESCRIPTION => $this->getRandomTextBlob(),
					Wish::PARAM_PROJECTS => implode( ',', is_string( $projects ) ? [ $projects ] : $projects ),
					Wish::PARAM_OTHER_PROJECT => $this->getRandomTextBlob( 1, 1, 3, true ),
					Wish::PARAM_AUDIENCE => $this->getRandomTextBlob( 1, 1, 5 ),
					Wish::PARAM_PHAB_TASKS => implode( ',', array_map(
						static fn ( $_i ) => 'T' . rand( 1000, 9999 ),
						range( 1, rand( 0, 3 ) )
					) ),
					Wish::PARAM_FOCUS_AREA => count( $focusAreaIds ) ?
						$focusAreaIds[array_rand( $focusAreaIds )] :
						null,
					Wish::PARAM_PROPOSER => $user->getName(),
					Wish::PARAM_CREATED => $this->getRandomTimestamp(),
					Wish::PARAM_BASE_LANG => $lang,
				],
				$config,
				$user
			);
			$pageUpdaterFactory->newPageUpdater( $wish->getPage(), $user )
				->setContent( SlotRecord::MAIN, $wish->toWikitext( $config ) )
				->saveRevision( 'Creating test wish' );

			$this->output( "Created wish: $title\n" );
		}
	}

	private function getRandomTextBlob(
		int $numParagraphs = 3,
		int $numSentences = 10,
		int $numWords = 15,
		bool $allowEmpty = false
	): string {
		if ( $allowEmpty && rand( 0, 1 ) === 0 ) {
			return '';
		}
		$words = [
			'Lorem', 'ipsum', 'dolor', 'sit', 'amet',
			'consectetur', 'adipiscing', 'elit', 'sed', 'do',
			'eiusmod', 'tempor', 'incididunt', 'ut', 'labore',
			'et', 'dolore', 'magna', 'aliqua'
		];
		$out = '';
		for ( $p = 0; $p < rand( 1, $numParagraphs ); $p++ ) {
			$paragraph = '';
			for ( $s = 0; $s < rand( min( 5, $numSentences ), $numSentences ); $s++ ) {
				$sentenceLength = rand( min( 5, $numWords ), $numWords );
				$sentence = implode( ' ', array_map(
					static fn ( $_i ) => $words[array_rand( $words )],
					range( 1, $sentenceLength )
				) );
				$paragraph .= ucfirst( $sentence ) . '. ';
			}
			$out .= trim( $paragraph ) . "\n\n";
		}

		return trim( $out );
	}

	private function getRandomTimestamp(): string {
		return wfTimestamp( TS_ISO_8601, mt_rand( 1735707600, time() ) );
	}
}

// @codeCoverageIgnoreStart
$maintClass = CreateTestWishlist::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
