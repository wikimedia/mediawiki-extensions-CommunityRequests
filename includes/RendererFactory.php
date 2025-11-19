<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaIndexRenderer;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaRenderer;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Vote\VoteRenderer;
use MediaWiki\Extension\CommunityRequests\Wish\WishIndexRenderer;
use MediaWiki\Extension\CommunityRequests\Wish\WishRenderer;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use Psr\Log\LoggerInterface;

/**
 * Functions for generating HTML on the wish and focus area pages
 */
class RendererFactory {

	public function __construct(
		private readonly WishlistConfig $config,
		private readonly WishStore $wishStore,
		private readonly FocusAreaStore $focusAreaStore,
		private readonly LoggerInterface $logger,
		private readonly LinkRenderer $linkRenderer,
	) {
	}

	/**
	 * The {{#CommunityRequests:}} parser function callback
	 *
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args
	 * @return array|string
	 */
	public function render( Parser $parser, PPFrame $frame, array $args ): array|string {
		$entityType = trim( $frame->expand( $args[0] ) );
		$renderer = $this->maybeGetInstance( $parser, $frame, $args, $entityType );
		if ( $renderer ) {
			return [
				$renderer->render(),
				'isHTML' => true
			];
		} else {
			$errorRenderer = new WishRenderer(
				$this->config,
				$this->wishStore,
				$this->focusAreaStore,
				$this->logger,
				$this->linkRenderer,
				$parser,
				$frame,
				$args
			);
			return $errorRenderer->getErrorMessage( 'communityrequests-unknown-parser-function', $entityType );
		}
	}

	/**
	 * Get a parser function renderer, or null if there is no such renderer type
	 *
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args
	 * @param string $rendererType
	 * @return AbstractRenderer|null
	 */
	protected function maybeGetInstance(
		Parser $parser,
		PPFrame $frame,
		array $args,
		string $rendererType
	): ?AbstractRenderer {
		$constructorArgs = [
			$this->config,
			$this->wishStore,
			$this->focusAreaStore,
			$this->logger,
			$this->linkRenderer,
			$parser,
			$frame,
			$args
		];
		return match ( $rendererType ) {
			'wish' => new WishRenderer( ...$constructorArgs ),
			'wish-index' => new WishIndexRenderer( ...$constructorArgs ),
			'focus-area' => new FocusAreaRenderer( ...$constructorArgs ),
			'focus-area-index' => new FocusAreaIndexRenderer( ...$constructorArgs ),
			'vote' => new VoteRenderer( ...$constructorArgs ),
			'data' => new EntityDataRenderer( ...$constructorArgs ),
			default => null,
		};
	}
}
