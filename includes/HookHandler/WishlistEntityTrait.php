<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use InvalidArgumentException;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Html\Html;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageReference;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFormatter;
use MessageLocalizer;
use Psr\Log\LoggerInterface;

trait WishlistEntityTrait {

	private readonly WishlistConfig $config;
	private readonly WishStore $wishStore;
	private readonly FocusAreaStore $focusAreaStore;
	private readonly TitleFormatter $titleFormatter;
	private readonly LoggerInterface $logger;

	/**
	 * @var array<string, AbstractWishlistEntity> Cache of loaded entities by page ID/language.
	 * @todo Use a persistent cache and make use of it in the stores.
	 */
	private static array $entities = [];

	/**
	 * Get a possibly cached entity for the given page ID and language,
	 * or load it and cache it if not already cached.
	 *
	 * @param PageIdentity $identity
	 * @param string $lang
	 * @return ?AbstractWishlistEntity
	 */
	protected function getMaybeCachedEntity( PageIdentity $identity, string $lang ): ?AbstractWishlistEntity {
		$canonicalPage = $this->getCanonicalEntityPage( $identity );
		$cacheKey = $identity->getId() . '|' . $lang;
		if ( isset( static::$entities[$cacheKey] ) ) {
			$entity = static::$entities[$cacheKey];
			$this->logger->info( __METHOD__ . ": Cache hit for {$entity->getPage()} with key $cacheKey\n" );
		} else {
			$this->logger->info( __METHOD__ . ": Cache miss for $canonicalPage with key $cacheKey\n" );
			$entity = $this->getStoreForPage( $canonicalPage )->get( $canonicalPage, $lang );
			if ( $entity ) {
				static::$entities[$cacheKey] = $entity;
			}
		}

		if ( !$entity ) {
			$this->logger->info( __METHOD__ . ": Entity not found for page {$canonicalPage}\n" );
			return null;
		}

		return $entity;
	}

	/**
	 * Clear the entity cache.
	 *
	 * @internal For use by unit tests only.
	 */
	public static function clearEntityCache(): void {
		static::$entities = [];
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
