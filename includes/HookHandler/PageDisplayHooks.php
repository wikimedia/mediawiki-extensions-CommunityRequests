<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Vote\Vote;
use MediaWiki\Extension\CommunityRequests\Vote\VoteStore;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\Hook\OutputPageParserOutputHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Page\Hook\BeforeDisplayNoArticleTextHook;
use MediaWiki\Page\PageReference;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\UserOptionsManager;
use ParserOutput;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Hook handlers involving page display, including adding ResourceLoader modules and JS config vars.
 */
class PageDisplayHooks implements
	BeforeDisplayNoArticleTextHook,
	BeforePageDisplayHook,
	OutputPageParserOutputHook
{

	use WishlistEntityTrait;

	public function __construct(
		private readonly WishlistConfig $config,
		private readonly WishStore $wishStore,
		private readonly FocusAreaStore $focusAreaStore,
		private readonly VoteStore $voteStore,
		private readonly UserOptionsManager $userOptionsManager,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly LinkRenderer $linkRenderer,
		private readonly LoggerInterface $logger,
	) {
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
			$out->getRequest()->getSession()->get( CommunityRequestsHooks::SESSION_KEY )
		) {
			$postEditVal = $out->getRequest()->getSession()->get( CommunityRequestsHooks::SESSION_KEY );
			$out->getRequest()->getSession()->remove( CommunityRequestsHooks::SESSION_KEY );
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
				$entityStore = $this->getStoreForPage( $out->getTitle() );
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
				$this->config->isWishOrFocusAreaIndexPage( $out->getTitle() )
			) &&
			$this->userOptionsManager->getBoolOption( $out->getUser(), PreferencesHooks::PREF_MACHINETRANSLATION )
		) {
			$out->addModules( 'ext.communityrequests.mint' );
		}

		// Render-blocking CSS.
		$out->addModuleStyles( 'ext.communityrequests.styles' );
	}

	/**
	 * Add a link to the entity page atop talk pages, using the translated entity title as the label (T406993).
	 *
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$title = $outputPage->getTitle();
		if ( !$this->config->isEnabled() || !$title->isTalkPage() ) {
			return;
		}
		$subjectTitle = Title::newFromLinkTarget( $this->namespaceInfo->getSubjectPage( $title ) );
		if ( !$this->config->isWishOrFocusAreaPage( $subjectTitle ) || !$subjectTitle->exists() ) {
			return;
		}

		$store = $this->getStoreForPage( $subjectTitle );
		$entity = $store->get(
			$this->getCanonicalEntityPage( $subjectTitle ),
			$outputPage->getLanguage()->getCode()
		);
		if ( !$entity ) {
			$this->logger->error( __METHOD__ . ": Could not load entity from talk page {$title->toPageIdentity()}" );
			return;
		}

		$wishLink = $this->linkRenderer->makeKnownLink(
			$entity->getPage(),
			$this->config->getEntityWikitextVal( $entity->getPage() ) .
			$outputPage->msg( 'colon-separator' )->text() .
			$entity->getTitle(),
			[ 'class' => 'ext-communityrequests-talk-entity-link' ]
		);
		$proposerLink = '';
		if ( $entity instanceof Wish ) {
			$proposer = $entity->getProposer()->getName();
			$proposerLink = $outputPage->msg( 'signature', $proposer, $proposer )->parse();
		}
		$outputPage->prependHTML(
			Html::noticeBox(
				$outputPage->msg( "communityrequests-{$store->entityType()}-talk-subtitle" )
					->rawParams( $wishLink, $proposerLink )
					->parse(),
				'ext-communityrequests-entity-talk-header'
			)
		);
	}
}
