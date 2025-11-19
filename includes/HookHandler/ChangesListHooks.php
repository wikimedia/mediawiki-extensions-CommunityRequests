<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\CommunityRequests\WishlistEntityTrait;
use MediaWiki\Hook\ChangesListInsertArticleLinkHook;
use MediaWiki\Page\PageReference;
use MediaWiki\RecentChanges\ChangesList;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFormatter;
use Psr\Log\LoggerInterface;

/**
 * Hook handlers involving the appearance of entity pages in change lists such as recent changes and the watchlist.
 */
class ChangesListHooks implements ChangesListInsertArticleLinkHook {
	use WishlistEntityTrait;

	public function __construct(
		protected readonly WishlistConfig $config,
		protected readonly WishStore $wishStore,
		protected readonly FocusAreaStore $focusAreaStore,
		protected readonly TitleFormatter $titleFormatter,
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
		if ( $this->config->isEnabled() && $this->config->isWishOrFocusAreaPage( $pageRef ) ) {
			'@phan-var PageReference $pageRef';
			$title = Title::newFromPageReference( $pageRef );
			$entity = $this->getMaybeCachedEntity( $title, $changesList->getLanguage()->getCode() );
			if ( !$entity ) {
				$this->logger->error( __METHOD__ . ": Could not load entity for page {$title->toPageIdentity()}" );
				return;
			}
			$articlelink = $this->getEntityLink( $entity, $changesList );
		}
	}
}
