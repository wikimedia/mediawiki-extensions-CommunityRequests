<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\CommunityRequests\WishlistEntityTrait;
use MediaWiki\Hook\ContributionsLineEndingHook;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Pager\ContributionsPager;
use MediaWiki\RecentChanges\ChangesList;
use MediaWiki\RecentChanges\Hook\ChangesListInitRowsHook;
use MediaWiki\RecentChanges\Hook\ChangesListInsertArticleLinkHook;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleFormatter;
use Psr\Log\LoggerInterface;
use stdClass;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Hook handlers involving the appearance of entity pages in change lists such as recent changes and the watchlist.
 */
class ChangesListHooks implements
	ChangesListInsertArticleLinkHook,
	ChangesListInitRowsHook,
	ContributionsLineEndingHook
{
	use WishlistEntityTrait;

	public function __construct(
		protected readonly WishlistConfig $config,
		protected readonly WishStore $wishStore,
		protected readonly FocusAreaStore $focusAreaStore,
		protected readonly TitleFactory $titleFactory,
		protected readonly TitleFormatter $titleFormatter,
		protected WANObjectCache $cache,
		protected readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Customize article links for wish and focus area pages in change lists.
	 *
	 * @param ChangesList $changesList
	 * @param string &$articlelink HTML of link to page.
	 * @param string &$s HTML of row that is being constructed.
	 * @param RecentChange $rc
	 * @param bool $unpatrolled
	 * @param bool $watched
	 */
	public function onChangesListInsertArticleLink(
		$changesList, &$articlelink, &$s, $rc, $unpatrolled, $watched
	): void {
		$pageRef = $rc->getPage();
		if ( $this->config->isEnabled() && $pageRef && $this->config->isEntityPage( $pageRef ) ) {
			$title = $this->titleFactory->newFromPageReference( $pageRef );
			$entity = $this->getMaybeCachedEntity( $title, $changesList->getLanguage()->getCode() );
			if ( !$entity ) {
				$this->logger->error( __METHOD__ . ": Could not load entity for page {$title->toPageIdentity()}" );
				return;
			}
			$articlelink = $this->getEntityLink( $entity, $changesList );
		}
	}

	/**
	 * Prefetch entity data for any entity pages in RecentChanges, Watchlist, and RelatedChanges.
	 *
	 * @param ChangesList $changesList
	 * @param array $rows Rows of recent changes data as returned by RecentChangesQuery.
	 */
	public function onChangesListInitRows( $changesList, $rows ): void {
		$entityTitles = [];
		foreach ( $rows as $row ) {
			// Should hit or warm up Title cache, which would happen anyway on change lists.
			$title = $this->titleFactory->makeTitle( $row->rc_namespace, $row->rc_title );
			if ( $this->config->isEntityPage( $title ) ) {
				$entityTitles[] = $title;
			}
		}
		$this->prefetchBulkCachedEntityData( $entityTitles, $changesList->getLanguage()->getCode() );
	}

	/**
	 * Customize links for wish and focus area pages at Special:Contributions.
	 *
	 * @param ContributionsPager $pager
	 * @param string &$ret
	 * @param stdClass $row
	 * @param array &$classes
	 * @param array &$attribs
	 */
	public function onContributionsLineEnding( $pager, &$ret, $row, &$classes, &$attribs ): void {
		if ( !$this->config->isEnabled() || !isset( $row->page_id ) ) {
			return;
		}
		$pageIdentity = PageIdentityValue::localIdentity(
			(int)$row->page_id,
			(int)$row->page_namespace,
			$row->page_title
		);
		if ( !$this->config->isEntityPage( $pageIdentity ) ) {
			return;
		}
		$entity = $this->getMaybeCachedEntity( $pageIdentity, $pager->getLanguage()->getCode() );
		if ( !$entity ) {
			return;
		}
		// Feels hacky, but better than creating our own ContributionsPager subclass
		// solely to format title links. All ContributionsPager methods we call are public.
		$templateParams = $pager->getTemplateParams( $row, $classes );
		$templateParams['articleLink'] = $this->getEntityLink( $entity, $pager );
		$ret = $pager->getProcessedTemplate( $templateParams );
	}
}
