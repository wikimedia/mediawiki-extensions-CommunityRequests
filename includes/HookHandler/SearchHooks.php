<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Search\Hook\ShowSearchHitHook;
use MediaWiki\Specials\SpecialSearch;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFormatter;
use Psr\Log\LoggerInterface;
use SearchResult;

/**
 * Hook handlers for customizing search results of wishlist pages.
 */
class SearchHooks implements ShowSearchHitHook {

	use WishlistEntityTrait;

	public function __construct(
		private readonly WishlistConfig $config,
		private readonly WishStore $wishStore,
		private readonly FocusAreaStore $focusAreaStore,
		private readonly LoggerInterface $logger,
		private readonly TitleFormatter $titleFormatter,
	) {
	}

	/**
	 * Customize search results for wish and focus area pages.
	 *
	 * @param SpecialSearch $searchPage The Special:Search instance
	 * @param SearchResult $result The search result being rendered
	 * @param string[] $terms The search terms
	 * @param string &$link The HTML link for the title
	 * @param string &$redirect The redirect target, if any
	 * @param string &$section The section title, if any
	 * @param string &$extract The text extract
	 * @param string &$score The relevance score
	 * @param string &$size The size text
	 * @param string &$date The date text
	 * @param string &$related Related links
	 * @param string &$html The full HTML of the search result
	 */
	public function onShowSearchHit(
		$searchPage, $result, $terms, &$link, &$redirect, &$section,
		&$extract, &$score, &$size, &$date, &$related, &$html
	) {
		if ( !$this->config->isEnabled() || !$this->config->isWishOrFocusAreaPage( $result->getTitle() ) ) {
			return;
		}

		$resultTitle = $result->getTitle();
		$canonicalPageRef = $this->config->getCanonicalEntityPageRef( $resultTitle );
		if ( !$canonicalPageRef ) {
			$this->logger->error( 'Search result entity page not recognized: {0}', [ $resultTitle ] );
			return;
		}
		$canonicalTitle = Title::newFromPageReference( $canonicalPageRef );

		$entity = $this->getMaybeCachedEntity( $canonicalTitle, $searchPage->getContext()->getLanguage()->getCode() );
		if ( !$entity ) {
			$this->logger->error( 'Search result entity not found: {0}', [ $resultTitle ] );
			return;
		}

		$link = $this->getEntityLink( $entity, $searchPage );

		// Include wish count for focus areas.
		if ( $entity instanceof FocusArea ) {
			$wishCount = $this->focusAreaStore->getWishCounts( $entity );
			$size .= $searchPage->msg( 'comma-separator' )->escaped() .
				$searchPage->msg( 'communityrequests-wish-count' )
					->numParams( $wishCount )
					->params( $wishCount )
					->escaped();
		}
		// Include vote count for all entities.
		$voteCount = $entity->getVoteCount() ?? 0;
		$size .= $searchPage->msg( 'comma-separator' )->escaped() .
			$searchPage->msg( 'communityrequests-vote-count' )
				->numParams( $voteCount )
				->params( $voteCount )
				->escaped();
	}
}
