<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Html\Html;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use Psr\Log\LoggerInterface;

/**
 * Hook handlers for the <focus-area> tag.
 */
class FocusAreaHookHandler extends CommunityRequestsHooks implements
	ParserFirstCallInitHook,
	LinksUpdateCompleteHook
{
	public const FOCUS_AREA_TRACKING_CATEGORY = 'communityrequests-focus-area-category';

	public function __construct(
		protected WishlistConfig $config,
		FocusAreaStore $store,
		Config $mainConfig,
		LoggerInterface $logger
	) {
		parent::__construct( $config, $store, $mainConfig, $logger );
	}

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ): void {
		if ( !$this->config->isEnabled() ) {
			return;
		}
		$parser->setHook( 'focus-area', $this->renderFocusArea( ... ) );
	}

	/**
	 * Render the <focus-area> tag.
	 *
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public function renderFocusArea( $input, array $args, Parser $parser, PPFrame $frame ): string {
		if ( !$this->config->isEnabled() || !$this->config->isFocusAreaPage( $parser->getPage() ) ) {
			return '';
		}

		$this->addTrackingCategory( $parser, self::FOCUS_AREA_TRACKING_CATEGORY );

		// Add tracking category for missing critical data.
		$requiredFields = [
			FocusArea::TAG_ATTR_CREATED,
			FocusArea::TAG_ATTR_TITLE,
			FocusArea::TAG_ATTR_BASE_LANG,
		];
		$missingFields = array_diff( $requiredFields, array_keys( $args ) );
		if ( $missingFields ) {
			$this->addTrackingCategory( $parser, self::ERROR_TRACKING_CATEGORY );
			return Html::element( 'span', [ 'class' => 'error' ],
				'Missing required field(s): ' . implode( ', ', $missingFields ) );
		}

		// These need to be set here because we need them for display in ::renderFocusAreaInternal().
		$args[ 'updated' ] = $parser->getRevisionTimestamp();
		$args[ FocusArea::TAG_ATTR_CREATED ] ??= $args[ 'updated' ];

		$this->logger->debug( __METHOD__ . ": Rendering focus area. {0}", [ json_encode( $args ) ] );
		$parser->getOutput()->setExtensionData( self::EXT_DATA_KEY, $args );

		return $this->renderFocusAreaInternal( $input ?: '', $args, $parser );
	}

	private function renderFocusAreaInternal( string $input, array $args, Parser $parser ): string {
		// TODO: Implement!
		return '';
	}

	/** @inheritDoc */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) {
		if ( !$this->config->isEnabled() || !$this->config->isFocusAreaPage( $linksUpdate->getTitle() ) ) {
			return;
		}

		$data = $linksUpdate->getParserOutput()->getExtensionData( self::EXT_DATA_KEY );
		if ( !$data ) {
			return;
		}

		$focusArea = FocusArea::newFromWikitextParams(
			$this->getCanonicalWishlistPage( $linksUpdate->getTitle() ),
			$linksUpdate->getTitle()->getPageLanguage()->getCode(),
			$data,
			$this->config
		);
		$this->store->save( $focusArea );
	}
}
