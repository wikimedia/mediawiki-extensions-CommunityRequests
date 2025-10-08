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
use MediaWiki\Extension\CommunityRequests\AbstractRenderer;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\EntityFactory;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\RendererFactory;
use MediaWiki\Extension\CommunityRequests\Vote\Vote;
use MediaWiki\Extension\CommunityRequests\Vote\VoteStore;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\Translate\MessageLoading\MessageHandle;
use MediaWiki\Extension\Translate\Utilities\Utilities;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Hook\ParserAfterTidyHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\RecentChange_saveHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Page\Article;
use MediaWiki\Page\Hook\BeforeDisplayNoArticleTextHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageReference;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsExpensiveHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Storage\Hook\RevisionDataUpdatesHook;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MessageSpecifier;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IDBAccessObject;

class CommunityRequestsHooks implements
	BeforePageDisplayHook,
	ChangeTagsListActiveHook,
	LinksUpdateCompleteHook,
	ListDefinedTagsHook,
	LoginFormValidErrorMessagesHook,
	ParserFirstCallInitHook,
	RecentChange_saveHook,
	RevisionDataUpdatesHook,
	SkinTemplateNavigation__UniversalHook,
	ParserAfterTidyHook,
	GetUserPermissionsErrorsExpensiveHook,
	BeforeDisplayNoArticleTextHook,
	GetPreferencesHook
{

	public const WISHLIST_CHANGE_TAG = 'community-wishlist';
	public const PREF_MACHINETRANSLATION = 'usemachinetranslation';
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
	/** @var AbstractWishlistStore[] */
	private array $stores;

	/**
	 * Whether the user is allowed to manually edit wish and focus area pages.
	 * This is set to true when the user is editing a wish or focus area using the special pages,
	 * and in some tests.
	 */
	public static bool $allowManualEditing = false;

	public function __construct(
		protected readonly WishlistConfig $config,
		WishStore $wishStore,
		FocusAreaStore $focusAreaStore,
		private readonly VoteStore $voteStore,
		private readonly EntityFactory $entityFactory,
		LinkRenderer $linkRenderer,
		private readonly PermissionManager $permissionManager,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserOptionsManager $userOptionsManager,
		private readonly LoggerInterface $logger,
		Config $mainConfig,
		private readonly WikiPageFactory $wikiPageFactory,
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
		$this->stores = [
			'wish' => $wishStore,
			'focus-area' => $focusAreaStore
		];
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
	public function onRecentChange_save( $rc ) {
		$request = RequestContext::getMain()->getRequest();
		if ( $request->getSession()->get( self::SESSION_KEY ) ) {
			$rc->addTags( self::WISHLIST_CHANGE_TAG );
		}
	}

	/**
	 * Returns base language page identity for a wish or focus area page.
	 *
	 * @todo Replace with the static WishlistConfig::getCanonicalWishlistPage()
	 * @param PageIdentity $identity
	 * @return PageIdentity
	 */
	public function getCanonicalEntityPage( PageIdentity $identity ): PageIdentity {
		// Use the base non-translated page (if Translate is installed) or if $identity is a Vote page.
		if ( ( $this->translateInstalled &&
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
			Utilities::isTranslationPage( new MessageHandle( Title::castFromPageIdentity( $identity ) ) ) ) ||
			$this->config->isVotesPage( $identity )
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
		$method = __METHOD__;

		if ( !$renderedRevision->getRevision()->getParentId() &&
			$this->translateInstalled &&
			$this->pageLanguageUseDB
		) {
			$updates[] = new MWCallableUpdate( function () use ( $title, $renderedRevision, $method ) {
				$store = $this->getStoreForTitle( $title );
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
		if ( !$this->config->isEnabled() || !$this->config->isWishOrFocusAreaPage( $page ) ) {
			return;
		}
		$store = $this->getStoreForTitle( $page );
		$entity = $store->get(
			$this->getCanonicalEntityPage( $page ),
			Title::castFromPageIdentity( $page )->getPageLanguage()->getCode()
		);
		if ( $entity ) {
			$store->delete( $entity );
			$this->logger->debug( __METHOD__ . ': Deleted entity {0}', [ $entity->getPage()->__toString() ] );
		}
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->config->isEnabled() ||
			!(
				$this->config->isWishOrFocusAreaPage( $out->getTitle() ) ||
				$this->config->isWishOrFocusAreaIndexPage( $out->getTitle() )
			)
		) {
			return;
		}

		// Post-edit success message.
		if ( $this->config->isWishOrFocusAreaPage( $out->getTitle() ) &&
			$out->getRequest()->getSession()->get( self::SESSION_KEY )
		) {
			$postEditVal = $out->getRequest()->getSession()->get( self::SESSION_KEY );
			$out->getRequest()->getSession()->remove( self::SESSION_KEY );
			$out->addJsConfigVars( 'crPostEdit', $postEditVal );
			// The post-edit message is shown in the voting module.
			$out->addModules( 'ext.communityrequests.voting' );
		}

		// Voting module.
		if (
			( $this->config->isWishVotingEnabled() && $this->config->isWishPage( $out->getTitle() ) ) ||
			( $this->config->isFocusAreaVotingEnabled() && $this->config->isFocusAreaPage( $out->getTitle() ) )
		) {
			$out->addModules( 'ext.communityrequests.voting' );

			// If the user is logged in, determine if they have already voted on this entity.
			if ( $out->getUser()->isRegistered() ) {
				$entityStore = $this->getStoreForTitle( $out->getTitle() );
				$entity = $entityStore->get( $this->getCanonicalEntityPage( $out->getTitle() ) );
				if ( !$entity ) {
					// This should not happen, but bail out gracefully if it does.
					$this->logger->error(
						__METHOD__ . ': Could not load entity for page {0}',
						[ $out->getTitle()->toPageIdentity()->__toString() ]
					);
					return;
				}
				$votesSubpageRef = $this->config->getVotesPageRefForEntity( $entity->getPage() );
				'@phan-var PageReference $votesSubpageRef';
				$votesSubpage = Title::newFromPageReference( $votesSubpageRef );
				$userVoteData = $this->voteStore->getForUser( $entity, $out->getUser() )
					?->toArray( $this->config )
					?? [ Vote::PARAM_ENTITY => $this->config->getEntityWikitextVal( $entity->getPage() ) ];
				$userVoteData[Vote::PARAM_BASE_REV_ID] = $votesSubpage->getLatestRevID( IDBAccessObject::READ_LATEST );
				// Not used by the Vue app. Remove to avoid runtime warnings about extraneous props.
				unset( $userVoteData[Vote::PARAM_TIMESTAMP] );
				unset( $userVoteData[Vote::PARAM_BASE_REV_ID] );
				$out->addJsConfigVars( 'crVoteData', $userVoteData );
			}
		}

		// Machine translation module.
		if (
			// Do static checks first before querying user options.
			(
				$this->config->isWishOrFocusAreaPage( $out->getTitle() ) ||
				$this->config->isWishIndexPage( $out->getTitle() ) ||
				$this->config->isFocusAreaIndexPage( $out->getTitle() )
			) &&
			$this->userOptionsManager->getBoolOption( $out->getUser(), self::PREF_MACHINETRANSLATION )
		) {
			$out->addModules( 'ext.communityrequests.mint' );
		}

		// Render-blocking CSS.
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
		if ( !$data || !isset( $data[AbstractWishlistEntity::PARAM_ENTITY_TYPE] ) ) {
			return;
		}
		$store = $this->stores[$data[AbstractWishlistEntity::PARAM_ENTITY_TYPE]];
		$title = $linksUpdate->getTitle();
		// If this a /Votes page, we need to reload the full entity data.
		if ( $this->config->isVotesPage( $title ) ) {
			$data = $store->normalizeArrayValues( array_merge(
				$store->get( $this->getCanonicalEntityPage( $title ) )->toArray( $this->config ),
				$data
			), AbstractWishlistStore::ARRAY_DELIMITER_WIKITEXT );
		}
		$entity = $this->entityFactory->createFromParserData( $data, $this->getCanonicalEntityPage( $title ) );
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

	/**
	 * Add an "Edit with form" tab to the navigation of wish and focus area pages.
	 *
	 * Unless the user has the 'manually-edit-wishlist' right, existing edit tabs are hidden.
	 *
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !$this->config->isEnabled() || !$this->isEntityPageOrEditPage( $sktemplate->getTitle() ) ) {
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
		if ( !$sktemplate->getTitle()->isSpecialPage() && !$sktemplate->getTitle()->exists() ) {
			unset( $links['views']['edit'], $links['views']['ve-edit'] );
			return;
		}

		$tabs = $links['views'];

		// Remove existing "Edit" tabs unless the user has the 'manually-edit-wishlist' right.
		if ( !$this->permissionManager->userHasRight( $sktemplate->getUser(), 'manually-edit-wishlist' ) ) {
			unset( $tabs['edit'], $tabs['ve-edit'] );
		}

		// Focus areas can only be edited by users with the 'manage-wishlist' right.
		if ( $this->config->isFocusAreaPage( $sktemplate->getTitle() ) &&
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
				$this->config->isWishPage( $sktemplate->getTitle() ) ? 'WishlistIntake' : 'EditFocusArea'
			)?->getPageTitle( $this->config->getEntityWikitextVal( $sktemplate->getTitle() ) )
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
		$canonicalPage = $this->getCanonicalEntityPage( $sktemplate->getTitle() );
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
	 * Replace the vote- and wish-count strip markers with messages containing the counts.
	 *
	 * @param Parser $parser
	 * @param string &$text
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		if ( !$this->config->isEnabled() ) {
			return;
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
				$wishCountFormatted = $parser->getTargetLanguage()->formatNum( $wishCount );
				$msg = $parser->msg( 'communityrequests-focus-area-view-wishes', $wishCountFormatted, $wishCount );
				$text = str_replace( AbstractRenderer::getWishCountStripMarker( $faPageId ), $msg->parse(), $text );
			}
		}
	}

	/**
	 * Prevent manual editing of wish and focus area pages unless the user has the 'manually-edit-wishlist' right.
	 *
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param array|string|MessageSpecifier &$result
	 * @return bool
	 */
	public function onGetUserPermissionsErrorsExpensive( $title, $user, $action, &$result ): bool {
		if ( !$this->config->isEnabled() || !$this->isEntityPageOrEditPage( $title ) ) {
			return true;
		}
		if ( $action !== 'edit' ) {
			return true;
		}

		// If $allowManualEditing is set, it means the user is editing a wish or focus area using the form.
		if ( self::$allowManualEditing ) {
			return true;
		}

		$userHasRight = $this->permissionManager->userHasRight( $user, 'manually-edit-wishlist' );
		if ( !$userHasRight || !$title->exists() ) {
			$result = [];

			// Conditionally show messages based on rights or page existence (T403505).

			if ( !$userHasRight || !$title->exists() ) {
				// Message instructing users to use the Special page form.
				$result[] = [
					'communityrequests-cant-manually-edit',
					$this->specialPageFactory->getPage(
						$this->config->isWishPage( $title ) ? 'WishlistIntake' : 'EditFocusArea'
					)->getPageTitle( $this->config->getEntityWikitextVal( $title ) ),
				];
			}
			if ( !$userHasRight ) {
				// Standard message listing the user groups that are allowed to manually edit.
				$result[] = $this->permissionManager->newFatalPermissionDeniedStatus(
					'manually-edit-wishlist',
					RequestContext::getMain()
				)->getMessages()[0];
			}

			return false;
		}

		return true;
	}

	/**
	 * We implement this solely to replace the standard message that
	 * is shown when an entity does not exist.
	 *
	 * @param Article $article
	 * @return bool|void
	 */
	public function onBeforeDisplayNoArticleText( $article ) {
		if ( !$this->config->isEnabled() ||
			!$this->config->isWishOrFocusAreaPage( $article->getTitle() ) ||
			$article->getOldID()
		) {
			return true;
		}

		$isWish = $this->config->isWishPage( $article->getTitle() );
		$context = $article->getContext();
		$text = $context->msg( 'communityrequests-missing-' . ( $isWish ? 'wish' : 'focus-area' ) )
			->params( $this->specialPageFactory->getPage(
					$isWish ? 'WishlistIntake' : 'EditFocusArea'
				)->getPageTitle() )
			->plain();
		$dir = $context->getLanguage()->getDir();
		$context->getOutput()
			->addWikiTextAsInterface(
				Html::openElement( 'div', [
					'class' => "noarticletext mw-content-$dir",
					'dir' => $dir,
					'lang' => $context->getLanguage()->getHtmlCode(),
				] ) .
				$text .
				Html::closeElement( 'div' )
			);

		return false;
	}

	/**
	 * Add preference for machine translations.
	 *
	 * @param User $user
	 * @param array &$preferences
	 */
	public function onGetPreferences( $user, &$preferences ) {
		if ( !$this->config->isEnabled() ) {
			return;
		}
		$preferences[self::PREF_MACHINETRANSLATION] = [
			'type' => 'toggle',
			'label-message' => [
				'communityrequests-wishlist-machine-translation',
				( $this->translateInstalled ? 'Special:MyLanguage/' : '' ) . $this->config->getHomepage(),
			],
			'section' => 'personal/i18n',
		];
	}

	private function isEntityPageOrEditPage( PageIdentity $identity ): bool {
		return $this->config->isWishOrFocusAreaPage( $identity ) || (
			$identity->getNamespace() === NS_SPECIAL && (
				str_starts_with( $identity->getDBkey(), 'WishlistIntake' ) ||
				str_starts_with( $identity->getDBkey(), 'EditFocusArea' )
			)
		);
	}
}
