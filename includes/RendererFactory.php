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
use MediaWiki\Parser\ParserCoreTagHooks;
use MediaWiki\Parser\PPFrame;
use MediaWiki\User\UserFactory;
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
		private readonly UserFactory $userFactory,
		private readonly ParserCoreTagHooks $parserCoreTagHooks,
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
		// FIXME: Parser::useParsoidFragments() is marked as internal.
		if ( !$parser->useParsoidFragments() || $parser->getOutputType() !== Parser::OT_PREPROCESS ) {
			return $this->renderInternal( $parser, $frame, $args );
		}
		// FIXME: Use Parsoid-native parser function
		// Under Parsoid, the parser function runs in a preprocess-only parser that
		// defers extension tags as 'exttag' strip markers for Parsoid to handle.
		// Since we return fully rendered HTML, those markers would never be
		// unstripped and leak as UNIQ...QINU text. Switch to OT_HTML so extension
		// tags in entity fields are executed here, then unstrip before returning.
		$parser->setOutputType( Parser::OT_HTML );
		try {
			$ret = $this->renderInternal( $parser, $frame, $args );
		} finally {
			$parser->setOutputType( Parser::OT_PREPROCESS );
		}
		if ( is_array( $ret ) ) {
			$ret[0] = $parser->getStripState()->unstripBoth( $ret[0] );
			// ParserAfterTidy does not run under Parsoid, so replace the focus area
			// wish count markers here instead.
			$ret[0] = AbstractRenderer::replaceWishCountStripMarkers( $parser, $ret[0] );
		}
		return $ret;
	}

	private function renderInternal( Parser $parser, PPFrame $frame, array $args ): array|string {
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
				$this->userFactory,
				$this->parserCoreTagHooks,
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
			$this->userFactory,
			$this->parserCoreTagHooks,
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
