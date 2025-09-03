<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Maintenance;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\Translate\PageTranslation\PageMoveOperation;
use MediaWiki\Extension\Translate\PageTranslation\TranslatableBundleMover;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePage;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Page\MovePageFactory;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Storage\PageUpdaterFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use StatusValue;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class MigrateFromGadget extends Maintenance {
	private const SUMMARY = 'Migrating to CommunityRequests';

	/** @var MovePageFactory */
	private $movePageFactory;
	/** @var TranslatableBundleMover */
	private $bundleMover;
	/** @var FormatterFactory */
	private $formatterFactory;
	/** @var PageUpdaterFactory */
	private $pageUpdaterFactory;
	/** @var WishlistConfig */
	private $wishConfig;
	/** @var WishStore */
	private $wishStore;
	/** @var FocusAreaStore */
	private $focusAreaStore;
	/** @var UserFactory */
	private $userFactory;
	/** @var RevisionStore */
	private $revisionStore;

	/** @var array|null */
	private $areaIds;
	/** @var User|null */
	private $scriptUser;

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Migrate wishes and focus areas created by ' .
			'the wishlist intake gadget to this extension' );

		$this->addOption( 'dry-run',
			'Dry run only, do not perform any action' );

		$this->addOption( 'focus-areas',
			'Migrate all focus areas' );
		$this->addOption( 'focus-area',
			'Migrate the specified focus area page',
		false, true );
		$this->addOption( 'focus-area-prefix',
			'The title prefix for unmigrated focus areas',
				false, true );

		$this->addOption( 'wishes',
			'Migrate all wishes' );
		$this->addOption( 'wish',
			'Migrate the specified wish page',
			false, true );
		$this->addOption( 'wish-prefix',
			'The title prefix for unmigrated wish pages' );
	}

	private function initServices() {
		$services = $this->getServiceContainer();
		$this->bundleMover = $services->get( 'Translate:TranslatableBundleMover' );
		$this->movePageFactory = $services->getMovePageFactory();
		$this->formatterFactory = $services->getFormatterFactory();
		$this->pageUpdaterFactory = $services->getPageUpdaterFactory();
		$this->wishConfig = $services->get( 'CommunityRequests.WishlistConfig' );
		$this->wishStore = $services->get( 'CommunityRequests.WishStore' );
		$this->focusAreaStore = $services->get( 'CommunityRequests.FocusAreaStore' );
		$this->userFactory = $services->getUserFactory();
		$this->revisionStore = $services->getRevisionStore();
	}

	public function execute() {
		$this->initServices();

		$timeRegex = "/^(\| *created *= *)([0-9]+:[0-9]+, [0-9]+ \w+ [0-9]{4} \(\w+\)).*$/m";
		$focusAreaReplacements = [
			// Use the parser function
			'func' => [
				'~{{Community Wishlist/Focus area *($|\|)~m',
				'{{#CommunityRequests: focus-area$1',
			],
			// Created timestamp should be ISO-8601
			'created' => [
				$timeRegex,
				$this->convertTime( ... ),
			],
			// Add baselang parameter
			'baselang' => [
				'/^}}$/m',
				"| baselang = en\n}}",
			],
		];
		$wishReplacements = [
			// Use the parser function
			'func' => [
				'~{{Community Wishlist/Wish~',
				'{{#CommunityRequests: wish',
			],
			// Simplify proposer field
			'proposer' => [
				"/^\| *proposer *=.*(?i)\[\[User:([^|\]]+)(\||]]).*$/m",
				'\| proposer = $1',
			],
			// Created timestamp should be ISO-8601
			'created' => [
				$timeRegex,
				$this->convertTime( ... ),
			],
			// Map focus area title to ID
			'area' => [
				'/^(\| *area *= *)(.+)$/m',
				function ( $m ) {
					$id = $this->getFocusAreaDisplayIdByTitle( trim( $m[2] ) );
					if ( $id !== null ) {
						return $m[1] . $id;
					} else {
						return $m[0];
					}
				},
			],
			// Don't complain if the focus area was empty
			'silence area' => [
				'/^\| *area *= *$/m',
				null,
			],
		];

		$done = false;
		$focusAreas = [];
		if ( $this->hasOption( 'focus-areas' ) ) {
			$oldAreaPrefix = $this->normalizeTitleInput(
				$this->getOption( 'focus-area-prefix', 'Community_Wishlist/Focus_areas/' )
			);
			$newAreaPrefix = $this->focusAreaStore->getPagePrefix();
			$focusAreas = $this->getPagesWithPrefix( $oldAreaPrefix, $newAreaPrefix );
		}
		if ( $this->hasOption( 'focus-area' ) ) {
			$focusAreas[] = $this->normalizeTitleInput( $this->getOption( 'focus-area' ) );
		}
		if ( $focusAreas ) {
			$this->migratePages(
				$this->focusAreaStore,
				$focusAreaReplacements,
				$focusAreas
			);
			$done = true;
		}

		$wishes = [];
		if ( $this->hasOption( 'wishes' ) ) {
			$oldWishPrefix = $this->normalizeTitleInput(
				$this->getOption( 'wish-prefix', 'Community_Wishlist/Wishes/' )
			);
			$newWishPrefix = $this->wishStore->getPagePrefix();
			$wishes = $this->getPagesWithPrefix( $oldWishPrefix, $newWishPrefix );
		}
		if ( $this->hasOption( 'wish' ) ) {
			$wishes[] = $this->normalizeTitleInput( $this->getOption( 'wish' ) );
		}
		if ( $wishes ) {
			$this->migratePages(
				$this->wishStore,
				$wishReplacements,
				$wishes
			);
			$done = true;
		}
		if ( !$done ) {
			$this->fatalError( "No target specified.\n" );
		}
	}

	/**
	 * Convert spaces to underscores and ensure the main namespace is used
	 *
	 * @param string $str
	 * @return string
	 */
	private function normalizeTitleInput( $str ) {
		if ( str_contains( $str, ':' ) ) {
			$this->fatalError( 'Titles with colons are not supported: ' .
				'wishes must be in the main namespace' );
		}
		return str_replace( ' ', '_', $str );
	}

	/**
	 * Migrate a set of focus areas or wishes
	 *
	 * @param AbstractWishlistStore $store
	 * @param array $replacements
	 * @param string[] $titleTexts
	 */
	private function migratePages( $store, $replacements, $titleTexts ) {
		$titlesDone = [];
		foreach ( $titleTexts as $titleText ) {
			$title = Title::newFromText( $titleText );
			if ( str_starts_with( $titleText, $store->getPagePrefix() )
				|| isset( $titlesDone[$titleText] )
			) {
				// Already migrated
				continue;
			} elseif ( TranslatablePage::isSourcePage( $title ) ) {
				// Migrate the whole bundle
				$this->migrateTranslateBundle( $store, $replacements, $titleText, $titlesDone );
			} elseif ( TranslatablePage::isTranslationPage( $title ) ) {
				// Will be migrated as part of its bundle
				continue;
			} else {
				$this->migrateUntranslated( $store, $replacements, $titleText );
			}
		}
	}

	/**
	 * Find pages in the main namespace matching the given prefix, but not
	 * matching another prefix
	 *
	 * @param string $prefix The prefix in DB key form
	 * @param string $excludePrefix
	 * @return string[]
	 */
	private function getPagesWithPrefix( string $prefix, string $excludePrefix ) {
		$dbr = $this->getDB( DB_REPLICA );
		return $dbr->newSelectQueryBuilder()
			->select( 'page_title' )
			->from( 'page' )
			->where( [
				'page_namespace' => NS_MAIN,
				'page_is_redirect' => 0,
				$dbr->expr(
					'page_title',
					IExpression::LIKE,
					new LikeValue(
						$prefix,
						$dbr->anyString()
					)
				),
				$dbr->expr(
					'page_title',
					IExpression::NOT_LIKE,
					new LikeValue(
						$excludePrefix,
						$dbr->anyString()
					)
				),
			] )
			// Roughly order by creation timestamp so that wish IDs will be in
			// ascending order of creation
			->orderBy( 'page_id' )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	/**
	 * Convert a timestamp from the usual LanguageEn form to ISO-8601.
	 * This is a callback for preg_replace_callback().
	 *
	 * @param array $m Captured matches
	 * @return string
	 */
	private function convertTime( $m ) {
		$unix = strtotime( $m[2] );
		if ( $unix ) {
			return $m[1] . gmdate( 'Y-m-d\TH:i:s\Z', $unix );
		} else {
			return $m[0];
		}
	}

	/**
	 * Move a page which is not part of a Translate bundle, and convert its text
	 *
	 * @param AbstractWishlistStore $store
	 * @param array $replacements
	 * @param string $titleText
	 */
	private function migrateUntranslated(
		AbstractWishlistStore $store,
		array $replacements,
		string $titleText
	) {
		$sourceTitle = Title::newFromText( $titleText );
		if ( !$sourceTitle ) {
			print "Skipping invalid title $titleText\n";
			return;
		}
		if ( !$this->pageHasRegex( $sourceTitle, $replacements['func'][0] ) ) {
			print "Skipping page with no template: $titleText\n";
			return;
		}
		if ( $this->isDryRun() ) {
			$destTitle = Title::newFromText( $store->getPagePrefix() . '(id)' );
			print "DRY RUN Would move $titleText to $destTitle\n";
			$editTitle = $sourceTitle;
		} else {
			$destTitle = $this->acquireDestTitle( $store );
			$status = $this->moveAssociatedPages( $sourceTitle, $destTitle );
			print "Untranslated move $titleText -> $destTitle: ";
			if ( $status->isOK() ) {
				print "OK\n";
				$editTitle = $destTitle;
			} else {
				print "FAILED " . $this->formatStatus( $status ) . "\n";
				return;
			}
		}
		$this->doConversionEdit( $replacements, $editTitle );
	}

	/**
	 * Move a page, its talk page, and any subpages
	 *
	 * @param Title $sourceTitle
	 * @param Title $destTitle
	 * @return Status
	 */
	private function moveAssociatedPages( Title $sourceTitle, Title $destTitle ) {
		$ops = [ $this->movePageFactory->newMovePage( $sourceTitle, $destTitle ) ];
		$sourceTalk = $sourceTitle->getTalkPageIfDefined();
		$destTalk = $destTitle->getTalkPageIfDefined();
		if ( $sourceTalk?->exists() && $destTalk?->exists() ) {
			$ops[] = $this->movePageFactory->newMovePage( $sourceTalk, $destTalk );
		}
		$status = Status::newGood();
		foreach ( $ops as $movePage ) {
			$status->merge( $movePage->move( $this->getScriptUser(), self::SUMMARY ) );
			$status->merge( $movePage->moveSubpages( $this->getScriptUser(), self::SUMMARY ) );
		}
		return $status;
	}

	/**
	 * Format a StatusValue
	 *
	 * @param StatusValue $status
	 * @return string
	 */
	private function formatStatus( $status ) {
		return $this->formatterFactory->getStatusFormatter( RequestContext::getMain() )
			->getWikiText( $status );
	}

	/**
	 * Move a translate bundle and convert the text
	 *
	 * @param AbstractWishlistStore $store
	 * @param array $replacements
	 * @param string $titleText
	 * @param array<string,bool> &$titlesDone
	 */
	private function migrateTranslateBundle(
		AbstractWishlistStore $store,
		array $replacements,
		string $titleText,
		array &$titlesDone
	) {
		$sourceTitle = Title::newFromText( $titleText );
		if ( !$sourceTitle ) {
			print "Skipping invalid title $titleText\n";
			return;
		}
		if ( !$this->pageHasRegex( $sourceTitle, $replacements['func'][0] ) ) {
			print "Skipping page with no template: $titleText\n";
			return;
		}

		if ( $this->isDryRun() ) {
			$destTitle = Title::newFromText( $store->getPagePrefix() . '(id)' );
		} else {
			$destTitle = $this->acquireDestTitle( $store );
		}
		if ( !$destTitle ) {
			print "Skipping invalid destination title for page $titleText\n";
			return;
		}

		$pageCollection = $this->bundleMover->getPageMoveCollection(
			$sourceTitle,
			$destTitle,
			$this->getScriptUser(),
			self::SUMMARY,
			true,
			true,
			true
		);
		$pagesToMove = $pageCollection->getListOfPages();
		$pagesToRedirect = $pageCollection->getListOfPagesToRedirect();

		if ( $this->isDryRun() ) {
			print "DRY RUN: Would move bundle of " . count( $pagesToMove ) . " pages with base $titleText\n";
			$editTitle = $sourceTitle;
		} else {
			print "Moving bundle of " . count( $pagesToMove ) . " pages with base $titleText\n";

			$this->bundleMover->moveSynchronously(
				$sourceTitle,
				$destTitle,
				$pagesToMove,
				$pagesToRedirect,
				$this->getScriptUser(),
				self::SUMMARY
			);
			$editTitle = $destTitle;
		}

		/** @var PageMoveOperation[] $pairs */
		$pairs = [
			...$pageCollection->getTranslationPagesPair(),
			...$pageCollection->getUnitPagesPair(),
			...$pageCollection->getSubpagesPair(),
		];
		foreach ( $pairs as $pair ) {
			$titlesDone[$pair->getOldTitle()->getPrefixedDBkey()] = true;
		}

		$this->doConversionEdit( $replacements, $editTitle );
	}

	/**
	 * Check if a title looks like it has a wish or focus area on it
	 *
	 * @param Title $title
	 * @param string $regex
	 * @return bool
	 */
	private function pageHasRegex( Title $title, $regex ): bool {
		$rev = $this->revisionStore->getKnownCurrentRevision( $title );
		if ( !$rev ) {
			return false;
		}
		$content = $rev->getContent( SlotRecord::MAIN );
		if ( !( $content instanceof WikitextContent ) ) {
			return false;
		}
		$text = $content->getText();
		return (bool)preg_match( $regex, $text );
	}

	/**
	 * Get a new ID for a focus area or wish and construct a Title
	 *
	 * @param AbstractWishlistStore $store
	 * @return Title
	 */
	private function acquireDestTitle( AbstractWishlistStore $store ): Title {
		return Title::newFromText(
			$store->getPagePrefix() . $store->getNewId()
		);
	}

	/**
	 * Apply regex replacements to a page, performing an edit if any changes
	 * were made.
	 *
	 * @param array $replacements
	 * @param Title $title
	 */
	private function doConversionEdit( array $replacements, Title $title ) {
		$titleText = $title->getPrefixedDBkey();
		$pageUpdater = $this->pageUpdaterFactory->newPageUpdater(
			$title,
			$this->getScriptUser()
		);
		$oldRev = $pageUpdater->grabParentRevision();
		if ( !$oldRev ) {
			print "Can't load converted page $titleText\n";
			return;
		}
		$oldContent = $oldRev->getContent( SlotRecord::MAIN );
		if ( !( $oldContent instanceof WikitextContent ) ) {
			print "Skipping conversion of non-wikitext page $titleText\n";
			return;
		}
		$oldText = $oldContent->getText();
		$done = $notDone = [];
		$newText = $this->convertText( $replacements, $oldText, $done, $notDone );
		if ( $oldText === $newText ) {
			print "No template found in $titleText\n";
			return;
		}
		$pageUpdater->setContent( SlotRecord::MAIN, new WikitextContent( $newText ) );
		if ( $this->isDryRun() ) {
			print "DRY RUN: ";
		} else {
			$newRev = $pageUpdater->saveRevision( self::SUMMARY, EDIT_UPDATE );
			if ( !$newRev ) {
				print "Edit failed for $titleText\n";
				return;
			}
		}
		if ( $notDone === [] ) {
			print "Successfully edited $titleText\n";
		} else {
			$doneText = implode( ', ', $done );
			$notDoneText = implode( ', ', $notDone );
			print "Partially edited $titleText: converted $doneText; NOT $notDoneText\n";
		}
	}

	/**
	 * Perform the text processing part of the conversion edit
	 *
	 * @param array $replacements
	 * @param string $text
	 * @param array &$done [out] Keys in $replacements which were matched and acted on
	 * @param array &$notDone [out] Keys in $replacements which were not matched
	 * @return string The new text
	 */
	private function convertText( array $replacements, string $text, array &$done, array &$notDone ) {
		foreach ( $replacements as $tag => [ $search, $replacement ] ) {
			if ( str_starts_with( $tag, 'silence ' ) ) {
				if ( preg_match( $search, $text ) ) {
					$notDone = array_diff( $notDone, [ substr( $tag, strlen( 'silence ' ) ) ] );
				}
				continue;
			}
			if ( is_string( $replacement ) ) {
				$newText = preg_replace( $search, $replacement, $text );
			} else {
				$newText = preg_replace_callback( $search, $replacement, $text );
			}
			if ( $text === $newText ) {
				$notDone[] = $tag;
			} else {
				$done[] = $tag;
			}
			$text = $newText;
		}
		return $text;
	}

	/**
	 * Find the ID of a previously migrated focus area
	 *
	 * @param string $title
	 * @return string|null
	 */
	private function getFocusAreaDisplayIdByTitle( string $title ): ?string {
		$normTitle = strtolower( trim( str_replace( '_', ' ', $title ) ) );
		$areas = $this->getFocusAreaIds();
		if ( isset( $areas[$normTitle] ) ) {
			return $areas[$normTitle];
		}
		return null;
	}

	/**
	 * Get the IDs of all migrated focus areas
	 *
	 * @return string[]
	 */
	private function getFocusAreaIds(): array {
		if ( $this->areaIds === null ) {
			/** @var FocusArea[] $areas */
			$areas = $this->focusAreaStore->getAll(
				'en',
				FocusAreaStore::createdField(),
				AbstractWishlistStore::SORT_ASC,
				10000
			);
			$this->areaIds = [];
			foreach ( $areas as $area ) {
				// Add the English translated title
				$normTitle = strtolower( $area->getTitle() );
				$title = Title::castFromPageIdentity( $area->getPage() );
				$id = $this->wishConfig->getEntityWikitextVal( $title );
				$this->areaIds[$normTitle] = $id;

				// Add any redirects to the focus area, indexed by their "slug"
				foreach ( $title->getRedirectsHere() as $redir ) {
					$normTitle = strtolower( $redir->getSubpageText() );
					$this->areaIds[$normTitle] = $id;
				}
			}
		}
		return $this->areaIds;
	}

	private function isDryRun(): bool {
		return $this->hasOption( 'dry-run' );
	}

	private function getScriptUser(): User {
		if ( !$this->scriptUser ) {
			$name = $this->getOption( 'user', 'Maintenance script' );
			if ( $this->isDryRun() ) {
				$this->scriptUser = $this->userFactory->newFromName( $name );
			} else {
				$this->scriptUser = User::newSystemUser( $name );
			}
		}
		return $this->scriptUser;
	}
}

$maintClass = MigrateFromGadget::class;
require_once RUN_MAINTENANCE_IF_MAIN;
