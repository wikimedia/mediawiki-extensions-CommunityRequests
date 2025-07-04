<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaTemplateRenderer;
use MediaWiki\Extension\CommunityRequests\Wish\WishTemplateRenderer;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use Psr\Log\LoggerInterface;

/**
 * Functions for generating HTML on the wish and focus area pages
 */
class TemplateRendererFactory {

	public function __construct(
		private readonly WishlistConfig $config,
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
			$this->addTrackingCategory( $parser, AbstractTemplateRenderer::ERROR_TRACKING_CATEGORY );
			return Html::element(
				'span',
				[ 'class' => 'error' ],
				"No such CommunityRequests entity type"
			);
		}
	}

	/**
	 * Get a template renderer, or null if there is no such entity type
	 *
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args
	 * @param string $entityType
	 * @return AbstractTemplateRenderer|null
	 */
	protected function maybeGetInstance(
		Parser $parser,
		PPFrame $frame,
		array $args,
		string $entityType
	): ?AbstractTemplateRenderer {
		return match ( $entityType ) {
			'wish' => new WishTemplateRenderer(
				$this->config,
				$this->logger,
				$this->linkRenderer,
				$parser,
				$frame,
				$args
			),
			'focus-area' => new FocusAreaTemplateRenderer(
				$this->config,
				$this->logger,
				$this->linkRenderer,
				$parser,
				$frame,
				$args
			),
			default => null,
		};
	}

	/**
	 * Adds a tracking category to the parser if the page is a wish or focus area page.
	 *
	 * @param Parser $parser
	 * @param string $category
	 */
	private function addTrackingCategory( Parser $parser, string $category ): void {
		if ( $this->config->isWishOrFocusAreaPage( $parser->getPage() ) ) {
			$parser->addTrackingCategory( $category );
		}
	}

}
