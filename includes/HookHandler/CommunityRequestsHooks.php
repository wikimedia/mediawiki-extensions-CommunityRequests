<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use InvalidArgumentException;
use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferrableUpdate;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Deferred\MWCallableUpdate;
use MediaWiki\Extension\CommunityRequests\AbstractTemplateRenderer;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\EntityFactory;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\TemplateRendererFactory;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\Translate\MessageLoading\MessageHandle;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePage;
use MediaWiki\Extension\Translate\Utilities\Utilities;
use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Hook\ParserAfterParseHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\RecentChange_saveHook;
use MediaWiki\Linker\LinkRenderer;
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
use RuntimeException;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
class CommunityRequestsHooks implements
	BeforePageDisplayHook,
	ChangeTagsListActiveHook,
	GetDoubleUnderscoreIDsHook,
	LinksUpdateCompleteHook,
	ListDefinedTagsHook,
	LoginFormValidErrorMessagesHook,
	ParserAfterParseHook,
	ParserFirstCallInitHook,
	RecentChange_saveHook,
	RevisionDataUpdatesHook
{

	public const WISHLIST_CHANGE_TAG = 'community-wishlist';
	public const MAGIC_MACHINETRANSLATION = 'machinetranslation';
	public const SESSION_KEY = 'communityrequests-intake';
	protected const EXT_DATA_KEY = AbstractTemplateRenderer::EXT_DATA_KEY;
	protected bool $translateInstalled;
	protected bool $pageLanguageUseDB;
	private TemplateRendererFactory $templateRendererFactory;
	/** @var AbstractWishlistStore[] */
	private array $stores;

	public function __construct(
		protected readonly WishlistConfig $config,
		WishStore $wishStore,
		FocusAreaStore $focusAreaStore,
		private readonly EntityFactory $entityFactory,
		LinkRenderer $linkRenderer,
		private readonly LoggerInterface $logger,
		Config $mainConfig
	) {
		$this->pageLanguageUseDB = $mainConfig->get( MainConfigNames::PageLanguageUseDB );
		try {
			$this->translateInstalled = ExtensionRegistry::getInstance()->isLoaded( 'Translate' );
		} catch ( RuntimeException ) {
			// Happens in unit tests.
			$this->translateInstalled = false;
		}
		$this->templateRendererFactory = new TemplateRendererFactory(
			$config,
			$this->logger,
			$linkRenderer
		);
		$this->stores = [
			'wish' => $wishStore,
			'focus-area' => $focusAreaStore
		];
	}

	/** @inheritDoc */
	public function onGetDoubleUnderscoreIDs( &$doubleUnderscoreIDs ) {
		if ( $this->config->isEnabled() ) {
			$doubleUnderscoreIDs[] = self::MAGIC_MACHINETRANSLATION;
		}
	}

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ) {
		if ( !$this->config->isEnabled() ) {
			return;
		}
		$parser->setFunctionHook(
			'communityrequests',
			$this->templateRendererFactory->render( ... ),
			Parser::SFH_OBJECT_ARGS
		);
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
				$store = $this->getStoreForTitle( $title );
				$parserOutput = $renderedRevision->getSlotParserOutput( SlotRecord::MAIN );
				$data = $parserOutput->getExtensionData( self::EXT_DATA_KEY );

				if ( $data &&
					$data[ AbstractWishlistEntity::PARAM_BASE_LANG ] !==
					Title::newFromText( $title->getText() )->getPageLanguage()->getCode() &&
					// @phan-suppress-next-line PhanUndeclaredClassMethod
					TranslatablePage::isTranslationPage( $title ) === false
				) {
					$store->setPageLanguage(
						$title->getId(),
						$data[ AbstractWishlistEntity::PARAM_BASE_LANG ]
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
		$store = $this->getStoreForTitle( $page );
		$entity = $store->get(
			$this->getCanonicalWishlistPage( $page ),
			Title::castFromPageIdentity( $page )->getPageLanguage()->getCode()
		);
		if ( $entity ) {
			$store->delete( $entity );
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
		} elseif (
			( $this->config->isWishVotingEnabled() && $this->config->isWishPage( $out->getTitle() ) ) ||
			( $this->config->isFocusAreaVotingEnabled() && $this->config->isFocusAreaPage( $out->getTitle() ) )
		) {
			// If the page is a wish, add the voting module.
			$out->addModules( 'ext.communityrequests.voting' );
		}

		$out->addModuleStyles( 'ext.communityrequests.styles' );
	}

	/**
	 * Using extension data saved in the ParserOutput, update the wish or focus
	 * area data in the database.
	 *
	 * @param LinksUpdate $linksUpdate
	 * @param mixed $ticket
	 */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) {
		if ( !$this->config->isEnabled() ) {
			return;
		}
		$data = $linksUpdate->getParserOutput()->getExtensionData( self::EXT_DATA_KEY );
		if ( !$data ) {
			return;
		}
		$store = $this->stores[ $data['entityType'] ];
		$title = $linksUpdate->getTitle();
		$entity = $this->entityFactory->createFromParserData(
			$data,
			$this->getCanonicalWishlistPage( $title ),
			$title->getPageLanguage()->getCode(),
		);
		$store->save( $entity );
	}

	/**
	 * Get a store for the given page title, or throw an exception if the title
	 * is not under any of the relevant prefixes.
	 *
	 * @param PageIdentity $title
	 * @return AbstractWishlistStore
	 * @throws InvalidArgumentException
	 */
	private function getStoreForTitle( PageIdentity $title ) {
		if ( $this->config->isWishPage( $title ) ) {
			return $this->stores['wish'];
		} elseif ( $this->config->isFocusAreaPage( $title ) ) {
			return $this->stores['focus-area'];
		} else {
			throw new InvalidArgumentException( 'title is not a wish or focus area' );
		}
	}

}
