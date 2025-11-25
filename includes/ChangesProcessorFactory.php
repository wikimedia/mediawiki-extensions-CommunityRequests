<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Content\Transform\ContentTransformer;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaChangesProcessor;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishChangesProcessor;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageParser;
use Psr\Log\LoggerInterface;

class ChangesProcessorFactory {

	public function __construct(
		protected readonly WishlistConfig $config,
		protected readonly WishStore $wishStore,
		protected readonly FocusAreaStore $focusAreaStore,
		protected readonly ContentTransformer $transformer,
		protected readonly ?TranslatablePageParser $translatablePageParser,
		protected readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Create a new changes processor for the given entity type.
	 *
	 * @param IContextSource $context
	 * @param AbstractWishlistEntity $entity The new entity.
	 * @param ?AbstractWishlistEntity $oldEntity The old entity to compare against, or null if none.
	 * @return WishChangesProcessor|FocusAreaChangesProcessor
	 */
	public function newChangesProcessor(
		IContextSource $context,
		AbstractWishlistEntity $entity,
		?AbstractWishlistEntity $oldEntity = null,
	): WishChangesProcessor|FocusAreaChangesProcessor {
		$class = $entity instanceof Wish ? WishChangesProcessor::class : FocusAreaChangesProcessor::class;
		$store = $entity instanceof Wish ? $this->wishStore : $this->focusAreaStore;
		return new $class(
			$this->config,
			$store,
			$this->transformer,
			$this->translatablePageParser,
			$this->logger,
			$context,
			$entity,
			$oldEntity,
		);
	}
}
