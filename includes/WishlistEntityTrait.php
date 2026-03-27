<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use InvalidArgumentException;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Html\Html;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageReference;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFormatter;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\WANObjectCache;

trait WishlistEntityTrait {

	public const CACHE_TTL = 60;
	public const CACHE_KEY = 'communityrequests-entity';

	protected readonly WishlistConfig $config;
	protected readonly WishStore $wishStore;
	protected readonly FocusAreaStore $focusAreaStore;
	protected readonly TitleFormatter $titleFormatter;
	protected WANObjectCache $cache;
	protected readonly LoggerInterface $logger;

	/**
	 * @var array<string, AbstractWishlistEntity> Cache of loaded entities by page ID/language.
	 */
	private static array $entityCache = [];

	/**
	 * Get a possibly cached entity for the given page ID and language,
	 * or load it and cache it if not already cached.
	 *
	 * @param PageIdentity $identity
	 * @param string $lang
	 * @return ?AbstractWishlistEntity
	 */
	protected function getMaybeCachedEntity( PageIdentity $identity, string $lang ): ?AbstractWishlistEntity {
		// @phan-suppress-next-line PhanRedundantCondition If $this->cache isn't set, we're lacking DI.
		if ( !isset( $this->cache ) ) {
			$this->logger->warning( self::class . ' should inject a $cache if it needs ::getMaybeCachedEntity()' );
			$this->cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		}
		$canonicalPage = $this->getCanonicalEntityPage( $identity );
		$cacheKey = $this->getCacheKey( $identity, $lang );
		if ( isset( static::$entityCache[$cacheKey] ) ) {
			$entity = static::$entityCache[$cacheKey];
			$this->logger->info( __METHOD__ . ": Static cache hit for $identity with key $cacheKey" );
		} else {
			$cachedData = $this->cache->get( $cacheKey );

			if ( $cachedData ) {
				$this->logger->info( __METHOD__ . ": Object cache hit for $identity with key $cacheKey" );
				$entityClass = $this->config->isWishPage( $identity ) ? Wish::class : FocusArea::class;
				$entity = $entityClass::newFromWikitextParams(
					$canonicalPage,
					$lang,
					$cachedData,
					$this->config
				);
			} else {
				$this->logger->info( __METHOD__ . ": Cache miss for $identity with key $cacheKey" );
				$entity = $this->getStoreForPage( $identity )->get( $canonicalPage, $lang );
			}

			if ( $entity ) {
				$this->setCache( $entity, $lang );
			}
		}

		if ( !$entity ) {
			$this->logger->info( __METHOD__ . ": Entity not found for page $identity" );
			return null;
		}

		return $entity;
	}

	/**
	 * @param PageIdentity[] $pages
	 * @param string $lang
	 * @return array
	 */
	protected function prefetchBulkCachedEntityData( array $pages, string $lang ): array {
		// Determine which entities still need to be loaded, keyed by entity type,
		// then page ID, with the row data as the values.
		$entitiesToLoad = [];
		foreach ( $pages as $page ) {
			$cacheKey = $this->getCacheKey( $page, $lang );
			if ( !isset( static::$entityCache[$cacheKey] ) ) {
				$loadKey = $this->config->isWishPage( $page ) ? 'wish' : 'focus-area';
				$entitiesToLoad[ $loadKey ][ $page->getId() ] = $page;
			}
		}

		if ( !$entitiesToLoad ) {
			$this->logger->debug(
				__METHOD__ . ": All entities already cached for given pages, or no entity pages found"
			);
			return [];
		}

		// Load missing entities in bulk.
		// TODO: This should be done in one query instead of two! This would require a large
		//   refactor of combining WishStore and FocusAreaStore into a single store.
		$entities = [];
		if ( isset( $entitiesToLoad['wish'] ) ) {
			$entities = array_merge( $entities, $this->wishStore->getAll(
				$lang,
				AbstractWishlistStore::ORDER_BY_DEFAULT,
				AbstractWishlistStore::SORT_ASC,
				count( $entitiesToLoad ),
				null,
				[ AbstractWishlistStore::FILTER_WISHES => array_keys( $entitiesToLoad['wish'] ) ],
				AbstractWishlistStore::FETCH_WIKITEXT_NONE,
			) );
		}
		if ( isset( $entitiesToLoad['focus-area'] ) ) {
			$entities = array_merge( $entities, $this->focusAreaStore->getAll(
				$lang,
				AbstractWishlistStore::ORDER_BY_DEFAULT,
				AbstractWishlistStore::SORT_ASC,
				count( $pages ),
				null,
				[ AbstractWishlistStore::FILTER_FOCUS_AREAS => array_keys( $entitiesToLoad['focus-area'] ) ],
				AbstractWishlistStore::FETCH_WIKITEXT_NONE,
			) );
		}

		foreach ( $entities as $entity ) {
			$this->setCache( $entity, $lang );
		}

		return $entities;
	}

	private function getCacheKey( PageIdentity $identity, string $lang ): string {
		return $this->cache->makeKey( self::CACHE_KEY, $identity->getId() . '|' . $lang );
	}

	private function setCache( AbstractWishlistEntity $entity, string $lang ): void {
		$cacheKey = $this->getCacheKey( $entity->getPage(), $lang );
		static::$entityCache[$cacheKey] = $entity;
		$this->cache->set(
			$cacheKey,
			$this->getStoreForPage( $entity->getPage() )
				->normalizeArrayValues(
					$entity->toArray( $this->config ),
					AbstractWishlistStore::ARRAY_DELIMITER_WIKITEXT
				),
			self::CACHE_TTL
		);
	}

	/**
	 * Clear the static entity cache.
	 *
	 * @internal For use by unit tests only.
	 */
	public static function clearEntityCache(): void {
		static::$entityCache = [];
	}

	/**
	 * Returns base language page identity for a wish or focus area page.
	 *
	 * @param PageIdentity $identity
	 * @return PageIdentity
	 */
	public function getCanonicalEntityPage( PageIdentity $identity ): PageIdentity {
		$pageRef = $this->config->getCanonicalEntityPageRef( $identity );
		return $pageRef ? Title::newFromPageReference( $pageRef ) : $identity;
	}

	/**
	 * Get a store for the given page title, or throw an exception if the title
	 * is not under any of the relevant prefixes.
	 *
	 * @param PageReference $page
	 * @return AbstractWishlistStore
	 * @throws InvalidArgumentException
	 */
	public function getStoreForPage( PageReference $page ): AbstractWishlistStore {
		if ( $this->config->isWishPage( $page ) ) {
			return $this->wishStore;
		} elseif ( $this->config->isFocusAreaPage( $page ) ) {
			return $this->focusAreaStore;
		} else {
			throw new InvalidArgumentException( 'title is not a wish or focus area' );
		}
	}

	/**
	 * Get a store for the given entity type, or throw an exception if the type
	 * is not recognized.
	 *
	 * @param string $entityType
	 * @return AbstractWishlistStore
	 * @throws InvalidArgumentException
	 */
	public function getStoreForType( string $entityType ): AbstractWishlistStore {
		if ( $entityType === 'wish' ) {
			return $this->wishStore;
		} elseif ( $entityType === 'focus-area' ) {
			return $this->focusAreaStore;
		} else {
			throw new InvalidArgumentException( 'entity type is not recognized' );
		}
	}

	/**
	 * Generate an HTML link for the given entity and linked page.
	 *
	 * @param AbstractWishlistEntity $entity
	 * @param MessageLocalizer $localizer
	 * @return string HTML link
	 */
	protected function getEntityLink( AbstractWishlistEntity $entity, MessageLocalizer $localizer ): string {
		$this->logger->debug( __METHOD__ . ": Generating link for entity {$entity->getPage()}" );
		$titleSpan = Html::element(
			'span',
			[ 'class' => 'ext-communityrequests-entity-link--label' ],
			$entity->getTitle()
		);
		$titleText = $this->titleFormatter->getFullText( $entity->getPage() );
		$entityIdSpan = Html::element(
			'span',
			[
				'class' => 'mw-title ext-communityrequests-entity-link--id',
				'style' => 'font-size: 0.85em;',
			],
			$localizer->msg( 'parentheses', $titleText )->text(),
		);

		return Html::rawElement(
			'a',
			[
				'href' => Title::newFromPageIdentity( $entity->getPage() )->getLocalURL(),
				'title' => $entity->getTitle(),
			],
			$titleSpan . ' ' . $entityIdSpan
		);
	}
}
