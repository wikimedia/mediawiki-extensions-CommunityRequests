<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferrableUpdate;
use MediaWiki\Deferred\MWCallableUpdate;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\Translate\MessageLoading\MessageHandle;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePage;
use MediaWiki\Extension\Translate\Utilities\Utilities;
use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Hook\ParserAfterParseHook;
use MediaWiki\Hook\RecentChange_saveHook;
use MediaWiki\Html\Html;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\Hook\RevisionDataUpdatesHook;
use MediaWiki\Title\Title;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
class CommunityRequestsHooks implements
	ChangeTagsListActiveHook,
	ListDefinedTagsHook,
	GetDoubleUnderscoreIDsHook,
	LoginFormValidErrorMessagesHook,
	RecentChange_saveHook,
	RevisionDataUpdatesHook,
	ParserAfterParseHook,
	BeforePageDisplayHook
{

	public const WISHLIST_CHANGE_TAG = 'community-wishlist';
	public const MAGIC_MACHINETRANSLATION = 'machinetranslation';
	public const ERROR_TRACKING_CATEGORY = 'communityrequests-error-category';
	public const SESSION_KEY = 'communityrequests-intake';
	protected const EXT_DATA_KEY = 'CommunityRequests-ext-data';
	protected bool $translateInstalled;
	protected bool $pageLanguageUseDB;
	private string $entityType;

	public function __construct(
		protected WishlistConfig $config,
		protected AbstractWishlistStore $store,
		Config $mainConfig,
		protected ?LoggerInterface $logger = null
	) {
		$this->pageLanguageUseDB = $mainConfig->get( MainConfigNames::PageLanguageUseDB );
		$this->entityType = strtolower( substr( strrchr( $this->store->entityClass(), '\\' ), 1 ) );
		if ( $this->logger === null ) {
			$this->logger = new NullLogger();
		}
		try {
			$this->translateInstalled = ExtensionRegistry::getInstance()->isLoaded( 'Translate' );
		} catch ( RuntimeException ) {
			// Happens in unit tests.
			$this->translateInstalled = false;
		}
	}

	/** @inheritDoc */
	public function onGetDoubleUnderscoreIDs( &$doubleUnderscoreIDs ) {
		if ( $this->config->isEnabled() ) {
			$doubleUnderscoreIDs[] = self::MAGIC_MACHINETRANSLATION;
		}
	}

	/** @inheritDoc */
	public function onParserAfterParse( $parser, &$text, $stripState ) {
		if ( !$this->config->isEnabled() ) {
			return;
		}
		if ( $parser->getOutput()->getPageProperty( self::MAGIC_MACHINETRANSLATION ) !== null ) {
			$parser->getOutput()->addModules( [ 'ext.communityrequests.mint' ] );
		}
	}

	/** @inheritDoc */
	public function onLoginFormValidErrorMessages( array &$messages ) {
		if ( $this->config->isEnabled() ) {
			$messages[] = 'communityrequests-please-log-in';
		}
	}

	/** @inheritDoc */
	public function onListDefinedTags( &$tags ) {
		if ( $this->config->isEnabled() ) {
			$tags[] = self::WISHLIST_CHANGE_TAG;
		}
	}

	/** @inheritDoc */
	public function onChangeTagsListActive( &$tags ) {
		if ( $this->config->isEnabled() ) {
			$tags[] = self::WISHLIST_CHANGE_TAG;
		}
	}

	/**
	 * Adds the self::WISHLIST_CHANGE_TAG tag to recent changes
	 * if the request was made using SpecialWishlistIntake.
	 *
	 * @param RecentChange $rc
	 */
	public function onRecentChange_Save( $rc ) {
		$request = RequestContext::getMain()->getRequest();
		if ( $request->getSession()->get( self::SESSION_KEY ) ) {
			$rc->addTags( self::WISHLIST_CHANGE_TAG );
		}
	}

	/**
	 * Returns base language page identity for a wish or focus area page.
	 *
	 * @param PageIdentity $identity
	 * @return PageIdentity
	 */
	public function getCanonicalWishlistPage( PageIdentity $identity ): PageIdentity {
		// Use the base non-translated page (if Translate is installed).
		if ( $this->translateInstalled &&
			// @phan-suppress-next-line PhanUndeclaredClassMethod
			Utilities::isTranslationPage( new MessageHandle( Title::castFromPageIdentity( $identity ) ) )
		) {
			$basePage = Title::newFromPageIdentity( $identity )->getBaseTitle();
			if ( $basePage->exists() ) {
				$identity = $basePage->toPageIdentity();
			}
		}
		return $identity;
	}

	/**
	 * Adds a tracking category to the parser if the page is a wish or focus area page.
	 *
	 * @param Parser $parser
	 * @param string $category
	 */
	protected function addTrackingCategory( Parser $parser, string $category ): void {
		if ( $this->config->isWishOrFocusAreaPage( $parser->getPage() ) ) {
			$parser->addTrackingCategory( $category );
		}
	}

	/**
	 * Set the page language for a wish or focus area page to the base language on initial creation.
	 *
	 * @param Title $title
	 * @param RenderedRevision $renderedRevision
	 * @param DeferrableUpdate[] &$updates
	 */
	public function onRevisionDataUpdates( $title, $renderedRevision, &$updates ) {
		if ( !$this->config->isEnabled() || !$this->config->isWishOrFocusAreaPage( $title ) ) {
			return;
		}

		if ( !$renderedRevision->getRevision()->getParentId() &&
			$this->translateInstalled &&
			$this->pageLanguageUseDB
		) {
			$updates[] = new MWCallableUpdate( function () use ( $title, $renderedRevision ) {
				$parserOutput = $renderedRevision->getSlotParserOutput( SlotRecord::MAIN );
				$data = $parserOutput->getExtensionData( self::EXT_DATA_KEY );

				if ( $data &&
					$data[ AbstractWishlistEntity::TAG_ATTR_BASE_LANG ] !==
						Title::newFromText( $title->getText() )->getPageLanguage()->getCode() &&
					// @phan-suppress-next-line PhanUndeclaredClassMethod
					TranslatablePage::isTranslationPage( $title ) === false
				) {
					$this->store->setPageLanguage(
						$title->getId(),
						$data[ AbstractWishlistEntity::TAG_ATTR_BASE_LANG ]
					);
				}
			}, __METHOD__ );
		}
	}

	/**
	 * Delete a wish or focus area and all its associated data.
	 * If the wish/FA is a translation, only the translations will be deleted.
	 *
	 * @param ProperPageIdentity $page
	 * @param Authority $deleter
	 * @param string $reason
	 * @param int $pageID
	 * @param RevisionRecord $deletedRev
	 * @param ManualLogEntry $logEntry
	 * @param int $archivedRevisionCount
	 * @return void
	 */
	public function onPageDeleteComplete(
		ProperPageIdentity $page,
		Authority $deleter,
		string $reason,
		int $pageID,
		RevisionRecord $deletedRev,
		ManualLogEntry $logEntry,
		int $archivedRevisionCount
	) {
		if ( !$this->config->isEnabled() || !$this->config->isWishOrFocusAreaPage( $page ) ) {
			return;
		}
		$entity = $this->store->get(
			$this->getCanonicalWishlistPage( $page ),
			Title::castFromPageIdentity( $page )->getPageLanguage()->getCode()
		);
		if ( $entity ) {
			$this->store->delete( $entity );
		}
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->config->isEnabled() || !$this->config->isWishOrFocusAreaPage( $out->getTitle() ) ) {
			return;
		}

		if ( $out->getRequest()->getSession()->get( self::SESSION_KEY ) ) {
			$postEditVal = $out->getRequest()->getSession()->get( self::SESSION_KEY );
			$out->getRequest()->getSession()->remove( self::SESSION_KEY );
			$out->addJsConfigVars( 'intakePostEdit', $postEditVal );
			$out->addModules( 'ext.communityrequests.intake' );
		}

		$out->addModuleStyles( 'ext.communityrequests.styles' );
	}

	// HTML helpers for rendering wish or focus area pages.

	protected function getParagraph( string $field, string $text ): string {
		return Html::element(
			'p',
			[ 'class' => "ext-communityrequests-{$this->entityType}--$field" ],
			$text
		);
	}

	protected function getParagraphRaw( string $field, string $html ): string {
		return Html::rawElement(
			'p',
			[ 'class' => "ext-communityrequests-{$this->entityType}--$field" ],
			$html
		);
	}

	protected function getListItem( string $field, Parser $parser, string $param ): string {
		return Html::rawElement(
			'li',
			[ 'class' => "ext-communityrequests-{$this->entityType}--$field" ],
			// Messages used here include:
			// * communityrequests-wish-created
			// * communityrequests-wish-updated
			// * communityrequests-wish-proposer
			$parser->msg( "communityrequests-wish-$field", $param )->parse()
		);
	}

	protected function getFakeButton( Parser $parser, Title $title, string $msgKey, string $icon ): string {
		return Html::rawElement( 'a',
			[
				'href' => $title->getLocalURL(),
				'class' => [
					'cdx-button', 'cdx-button--fake-button', 'cdx-button--fake-button--enabled',
					'cdx-button--action-default', 'cdx-button--weight-normal', 'cdx-button--enabled'
				],
				'role' => 'button',
			],
			Html::element( 'span',
				[
					'class' => [ 'cdx-button__icon', "ext-communityrequests-{$this->entityType}--$icon" ],
					'aria-hidden' => 'true',
				],
			) . $parser->msg( $msgKey )->escaped()
		);
	}

	protected function getVotingSection( Parser $parser, bool $votingEnabled, string $msgKey = 'wish' ): string {
		$out = Html::element( 'div', [
			'class' => 'mw-heading mw-heading2',
		], $parser->msg( "communityrequests-$msgKey-voting" )->text() );

		// TODO: Vote counting doesn't work yet (T388220)
		$out .= $this->getParagraphRaw( 'voting-desc',
			$parser->msg( "communityrequests-$msgKey-voting-info", 0, 0 )->parse() . ' ' . (
				$votingEnabled ?
					$parser->msg( "communityrequests-$msgKey-voting-info-open" )->escaped() :
					$parser->msg( "communityrequests-$msgKey-voting-info-closed" )->escaped()
			)
		);

		if ( $votingEnabled ) {
			// TODO: add an cwaction=vote page for no-JS users, or something.
			$out .= Html::element( 'button',
				[
					'class' => [
						'cdx-button', 'cdx-button--action-progressive', 'cdx-button--weight-primary',
						'ext-communityrequests-voting-btn'
					],
					'type' => 'button',
				],
				$parser->msg( "communityrequests-support-$msgKey" )->text()
			);
		}

		// Transclude the /Votes subpage if it exists.
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		$voteSubpagePath = Title::newFromPageReference( $parser->getPage() )->getPrefixedDBkey() .
			$this->config->getVotesPageSuffix();
		$voteSubpageTitle = Title::newFromText( $voteSubpagePath );
		if ( $voteSubpageTitle->exists() ) {
			$out .= $parser->recursiveTagParse( '{{:' . $voteSubpagePath . '}}' );
		}

		return $out;
	}
}
