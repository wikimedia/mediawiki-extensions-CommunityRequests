<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Deferred\DeferrableUpdate;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Deferred\MWCallableUpdate;
use MediaWiki\Extension\CommunityRequests\AbstractRenderer;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\EntityFactory;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\RendererFactory;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\CommunityRequests\WishlistEntityTrait;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Hook\ParserAfterTidyHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\PageStoreRecord;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Storage\Hook\RevisionDataUpdatesHook;
use MediaWiki\Title\Title;
use Psr\Log\LoggerInterface;

class CommunityRequestsHooks implements
	LinksUpdateCompleteHook,
	LoginFormValidErrorMessagesHook,
	ParserFirstCallInitHook,
	RevisionDataUpdatesHook,
	SkinTemplateNavigation__UniversalHook,
	ParserAfterTidyHook
{
	use WishlistEntityTrait;

	public const SESSION_KEY = 'communityrequests';
	protected const EXT_DATA_KEY = AbstractRenderer::EXT_DATA_KEY;
	public const SESSION_VALUE_ENTITY_CREATED = 'entity-created';
	public const SESSION_VALUE_ENTITY_UPDATED = 'entity-updated';
	public const SESSION_VALUE_VOTE_ADDED = 'vote-added';
	public const SESSION_VALUE_VOTE_UPDATED = 'vote-updated';
	public const SESSION_VALUE_VOTE_REMOVED = 'vote-removed';
	protected bool $translateInstalled;
	protected bool $pageLanguageUseDB;
	private RendererFactory $rendererFactory;

	public function __construct(
		protected readonly WishlistConfig $config,
		protected readonly WishStore $wishStore,
		protected readonly FocusAreaStore $focusAreaStore,
		protected readonly EntityFactory $entityFactory,
		protected readonly LinkRenderer $linkRenderer,
		protected readonly PermissionManager $permissionManager,
		protected readonly SpecialPageFactory $specialPageFactory,
		protected readonly LoggerInterface $logger,
		protected readonly Config $mainConfig,
		protected readonly WikiPageFactory $wikiPageFactory,
		?ExtensionRegistry $extensionRegistry = null
	) {
		$extensionRegistry ??= ExtensionRegistry::getInstance();
		$this->pageLanguageUseDB = $mainConfig->get( MainConfigNames::PageLanguageUseDB );
		$this->translateInstalled = $extensionRegistry->isLoaded( 'Translate' );
		$this->rendererFactory = new RendererFactory(
			$config,
			$wishStore,
			$focusAreaStore,
			$this->logger,
			$linkRenderer
		);
	}

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ) {
		if ( !$this->config->isEnabled() ) {
			return;
		}
		$parser->setFunctionHook(
			'communityrequests',
			$this->rendererFactory->render( ... ),
			Parser::SFH_OBJECT_ARGS
		);
	}

	/** @inheritDoc */
	public function onLoginFormValidErrorMessages( array &$messages ) {
		if ( $this->config->isEnabled() ) {
			$messages[] = 'communityrequests-please-log-in';
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
		if ( !$this->config->isEnabled() || !$this->config->isEntityPage( $title ) ) {
			return;
		}
		$method = __METHOD__;

		if ( !$renderedRevision->getRevision()->getParentId() &&
			$this->translateInstalled &&
			$this->pageLanguageUseDB
		) {
			$updates[] = new MWCallableUpdate( function () use ( $title, $renderedRevision, $method ) {
				$store = $this->getStoreForPage( $title );
				$parserOutput = $renderedRevision->getSlotParserOutput( SlotRecord::MAIN );
				$data = $parserOutput->getExtensionData( self::EXT_DATA_KEY );

				if ( $data &&
					// Only set the page language if it is not already set to the base language, and…
					$data[AbstractWishlistEntity::PARAM_BASE_LANG] !== $title->getPageLanguage()->getCode() &&
					// $title is the base page (not a translation subpage).
					$this->config->getCanonicalEntityPageRef( $title )->isSamePageAs( $title->toPageIdentity() )
				) {
					$store->setPageLanguage(
						$title->getId(),
						$data[AbstractWishlistEntity::PARAM_BASE_LANG]
					);
					// Keep track of the language change in the extension data to guard
					// against race conditions. This will be used later instead of fetching
					// page language from the Title object.
					$data[AbstractWishlistEntity::PARAM_LANG] = $data[AbstractWishlistEntity::PARAM_BASE_LANG];
					$parserOutput->setExtensionData( self::EXT_DATA_KEY, $data );

					$this->logger->debug(
						$method . ': Set page language for {0} to {1}',
						[ $title->toPageIdentity()->__toString(), $data[AbstractWishlistEntity::PARAM_BASE_LANG] ]
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
		if ( !$this->config->isEnabled() || !$this->config->isEntityPage( $page ) ) {
			return;
		}

		// First try to use the DB language of the deleted page.
		$lang = $page instanceof PageStoreRecord ? $page->getLanguage() : null;
		// Then try to infer from the page title.
		$lang ??= $this->config->getLanguageFromPage( $page );
		// Then fallback to using a Title object, which should be the site default language.
		$lang ??= Title::castFromPageIdentity( $page )->getPageLanguage()->getCode();
		if ( !$lang ) {
			$this->logger->error( __METHOD__ . ": Could not determine language for deleted page $page" );
			return;
		}

		$canonicalPage = $this->getCanonicalEntityPage( $page );
		$store = $this->getStoreForPage( $page );
		$store->delete( $canonicalPage->getId(), $lang );
		$this->logger->debug( __METHOD__ . ': Deleted entity {0}', [ $page->__toString() ] );
	}

	/**
	 * Using extension data saved in the ParserOutput, update the wish or focus
	 * area data in the database.
	 *
	 * @param LinksUpdate $linksUpdate
	 * @param mixed $ticket
	 */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) {
		$title = $linksUpdate->getTitle();
		if ( !$this->config->isEnabled() ||
			( !$this->config->isEntityPage( $title ) && !$this->config->isVotesPage( $title ) )
		) {
			return;
		}

		$canonicalPage = $this->getCanonicalEntityPage( $title );
		$data = $linksUpdate->getParserOutput()->getExtensionData( self::EXT_DATA_KEY );

		// If this a /Votes page, we need to reload the full entity data.
		if ( $this->config->isVotesPage( $title ) ) {
			// Always use the base language for votes pages.
			$baseLang = Title::castFromPageIdentity( $canonicalPage )->getPageLanguage()->getCode();
			$data[AbstractWishlistEntity::PARAM_LANG] = $baseLang;

			// The absence of entity type indicates that all votes were removed.
			if ( is_array( $data ) && !isset( $data[AbstractWishlistEntity::PARAM_ENTITY_TYPE] ) ) {
				// At this point $canonicalPage is either a wish or focus area.
				$entityType = $this->config->isWishPage( $canonicalPage ) ? 'wish' : 'focus-area';

				// Set entity type and reset vote count to zero.
				$data[AbstractWishlistEntity::PARAM_ENTITY_TYPE] = $entityType;
				$data[AbstractWishlistEntity::PARAM_VOTE_COUNT] = 0;
			}

			$store = $this->getStoreForType( $data[AbstractWishlistEntity::PARAM_ENTITY_TYPE] );
			$entity = $store->get( $canonicalPage );
			// Guard against the potential for editing the /Votes page of a non-existent entity.
			$data = $entity ?
				$store->normalizeArrayValues(
					array_merge( $entity->toArray( $this->config ), $data ),
					AbstractWishlistStore::ARRAY_DELIMITER_WIKITEXT
				) : null;
		}

		if ( !$data || !isset( $data[AbstractWishlistEntity::PARAM_ENTITY_TYPE] ) ) {
			return;
		}

		$store ??= $this->getStoreForType( $data[AbstractWishlistEntity::PARAM_ENTITY_TYPE] );
		$entity = $this->entityFactory->createFromParserData( $data, $canonicalPage );
		$this->logger->debug(
			__METHOD__ . ': Saving {0} with data: {1}',
			[ $title->toPageIdentity()->__toString(), json_encode( $data ) ]
		);
		$store->save( $entity );

		// (T404748) Update parser cache to ensure displayed content reflects content language
		if ( $this->translateInstalled ) {
			$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
			$wikiPage->doPurge();
			$wikiPage->updateParserCache();
		}
	}

	/**
	 * Add an "Edit with form" tab to the navigation of wish and focus area pages.
	 *
	 * Unless the user has the 'manually-edit-wishlist' right, existing edit tabs are hidden.
	 *
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !$this->config->isEnabled() || !$this->config->isEntityPage( $sktemplate->getRelevantTitle() ) ) {
			return;
		}

		$this->updateDiscussionLink( $sktemplate, $links );
		$this->updateEditLinks( $sktemplate, $links );
	}

	/**
	 * Update the edit links for the given skin template.
	 *
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 */
	private function updateEditLinks( SkinTemplate $sktemplate, array &$links ): void {
		// If the page doesn't exist, don't show any edit tabs. We do this even for privileged users,
		// as manual creation of entity pages could cause data integrity issues.
		if ( !$sktemplate->getTitle()->isSpecialPage() && !$sktemplate->getRelevantTitle()->exists() ) {
			unset( $links['views']['edit'], $links['views']['ve-edit'] );
			return;
		}

		$tabs = $links['views'];

		// Remove existing "Edit" tabs unless the user has the 'manually-edit-wishlist' right.
		if ( !$this->permissionManager->userHasRight( $sktemplate->getUser(), 'manually-edit-wishlist' ) ) {
			unset( $tabs['edit'], $tabs['ve-edit'] );
		}

		// Focus areas can only be edited by users with the 'manage-wishlist' right.
		if ( $this->config->isFocusAreaPage( $sktemplate->getRelevantTitle() ) &&
			!$this->permissionManager->userHasRight( $sktemplate->getUser(), 'manage-wishlist' )
		) {
			$links['views'] = $tabs;
			return;
		}

		$wishlistEditTab = [
			'text' => $sktemplate->msg( 'communityrequests-edit-with-form' )->text(),
			'icon' => 'edit',
			'class' => $sktemplate->getTitle()->isSpecialPage() ? 'selected' : '',
			'href' => $this->specialPageFactory->getPage(
				$this->config->isWishPage( $sktemplate->getRelevantTitle() ) ? 'WishlistIntake' : 'EditFocusArea'
			)?->getPageTitle( $this->config->getEntityWikitextVal( $sktemplate->getRelevantTitle() ) )
				->getLocalURL()
		];

		// Attempt to insert before the "View history" tab.
		$newTabs = [];
		foreach ( $tabs as $key => $val ) {
			if ( $key === 'history' ) {
				$newTabs['wishlist-edit'] = $wishlistEditTab;
			}
			$newTabs[$key] = $val;
		}
		// If no "View history" tab was found, append to the end.
		if ( !isset( $newTabs['wishlist-edit'] ) ) {
			$newTabs['wishlist-edit'] = $wishlistEditTab;
		}

		$links['views'] = $newTabs;
	}

	/**
	 * Update the discussion link for the given skin template.
	 *
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 */
	private function updateDiscussionLink( SkinTemplate $sktemplate, array &$links ): void {
		$canonicalPage = $this->getCanonicalEntityPage( $sktemplate->getRelevantTitle() );
		$talkPage = Title::newFromPageIdentity( $canonicalPage )->getTalkPageIfDefined();

		if ( !$talkPage ) {
			return;
		}

		$href = $talkPage->exists()
			? $talkPage->getLinkURL()
			: $talkPage->getLinkURL( [ 'action' => 'edit', 'redlink' => 1 ] );
		$class = $talkPage->exists() ? '' : 'new';

		// Skins build their links from different sources, so we need to set them all.
		$links['namespaces']['talk']['href'] = $href;
		$links['namespaces']['talk']['class'] = $class;
		$links['namespaces']['talk']['link-class'] = $class;
		$links['associated-pages']['talk']['href'] = $href;
		$links['associated-pages']['talk']['class'] = $class;
		$links['associated-pages']['talk']['link-class'] = $class;
	}

	/**
	 * Replace strip markers and set output language.
	 *
	 * @param Parser $parser
	 * @param string &$text
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		if ( !$this->config->isEnabled() ) {
			return;
		}

		if ( $this->config->isEntityPage( $parser->getPage() ) ||
			$this->config->isEntityIndexPage( $parser->getPage() )
		) {
			// Ensure the output language matches user interface language (T407349).
			$parser->getOutput()->setLanguage( $parser->getOptions()->getUserLangObj() );
		}

		$data = $parser->getOutput()->getExtensionData( self::EXT_DATA_KEY );
		if ( !$data
			|| ( !isset( $data[AbstractWishlistEntity::PARAM_ENTITY_TYPE] )
				&& !isset( $data[AbstractWishlistEntity::PARAM_WISH_COUNT] )
		) ) {
			return;
		}

		// Vote counts on wish and focus area pages.
		if ( isset( $data[AbstractWishlistEntity::PARAM_ENTITY_TYPE] ) ) {
			$voteCount = intval( $data[ AbstractWishlistEntity::PARAM_VOTE_COUNT ] ?? 0 );
			$this->logger->debug(
				__METHOD__ . ': Replacing voting strip marker in {0} with vote count {1}',
				[
					$parser->getPage()->__toString(),
					$voteCount
				]
			);
			$text = str_replace(
				AbstractRenderer::VOTING_STRIP_MARKER,
				// Messages used here:
				// * communityrequests-focus-area-voting-info
				// * communityrequests-wish-voting-info
				$parser->msg( "communityrequests-{$data[AbstractWishlistEntity::PARAM_ENTITY_TYPE]}-voting-info" )
					->numParams( $voteCount )
					->params( $voteCount )
					->inLanguage( $parser->getOptions()->getUserLangObj() )
					->parse(),
				$text
			);
		}

		// Wish counts.
		if ( isset( $data[AbstractWishlistEntity::PARAM_WISH_COUNT] ) ) {
			$this->logger->debug(
				__METHOD__ . ': Replacing wish count strip markers in {0} with wish counts',
				[ $parser->getPage()->__toString() ]
			);
			foreach ( $data[AbstractWishlistEntity::PARAM_WISH_COUNT] as $faPageId => $wishCount ) {
				$wishCountFormatted = $parser->getOptions()->getUserLangObj()->formatNum( $wishCount );
				$msg = $parser->msg( 'communityrequests-focus-area-view-wishes', $wishCountFormatted, $wishCount );
				$text = str_replace( AbstractRenderer::getWishCountStripMarker( $faPageId ), $msg->parse(), $text );
			}
		}
	}
}
