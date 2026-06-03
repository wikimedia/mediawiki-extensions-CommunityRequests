<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\CommunityRequests\WishlistEntityTrait;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererEndHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\RecentChanges\ChangesList;
use MediaWiki\RecentChanges\Hook\ChangesListInitRowsHook;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleFormatter;
use Psr\Log\LoggerInterface;
use Wikimedia\HtmlArmor\HtmlArmor;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Hook handlers involving the appearance of entity pages in change lists such as recent changes and the watchlist.
 */
class ChangesListHooks implements
	ChangesListInitRowsHook,
	HtmlPageLinkRendererEndHook
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
	 * Customize link rendering for wish and focus area pages wherever LinkRenderer is used,
	 * which includes change lists and other places.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/HtmlPageLinkRendererEnd
	 *
	 * @param LinkRenderer $linkRenderer
	 * @param LinkTarget $target
	 * @param bool $isKnown
	 * @param string|HtmlArmor &$text
	 * @param string[] &$attribs
	 * @param string &$ret
	 */
	public function onHtmlPageLinkRendererEnd( $linkRenderer, $target, $isKnown, &$text, &$attribs, &$ret ) {
		if ( !$this->config->isEnabled() || !$isKnown ) {
			return;
		}

		// Don't alter links in wikitext output, mainly because Parsoid-rendered
		// HTML is incompatible with this hook. See T343483 (WikiLambda) and T131176 (WikiBase).
		$context = RequestContext::getMain();
		if ( !$context->hasTitle() ) {
			return;
		}

		$targetTitle = Title::newFromLinkTarget( $target );
		if ( !$this->config->isEntityPage( $targetTitle ) ) {
			return;
		}

		// Don't re-write the label if the label is already set,
		// such as the "prev" and "cur" links on history pages, etc.
		// This is the same hack used by WikiBase and WikiLambda.
		if ( $text !== null && $targetTitle->getFullText() !== HtmlArmor::getHtml( $text ) ) {
			return;
		}

		$entity = $this->getMaybeCachedEntity( $targetTitle, $context->getLanguage()->getCode() );
		if ( !$entity ) {
			return;
		}

		// Return as HTML that shouldn't be escaped (safety is ensured by getEntityLink()).
		$text = new HtmlArmor( $this->getEntityLink( $entity, $context ) );
	}
}
