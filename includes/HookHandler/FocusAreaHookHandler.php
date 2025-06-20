<?php

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use Psr\Log\LoggerInterface;

/**
 * Hook handlers for the <focus-area> tag.
 */
class FocusAreaHookHandler extends CommunityRequestsHooks implements
	ParserFirstCallInitHook,
	LinksUpdateCompleteHook
{
	private const EXT_DATA_FOCUS_AREA_KEY = 'CommunityRequests-focus-area';
	private FocusAreaStore $focusAreaStore;

	/**
	 * @param WishlistConfig $config
	 * @param FocusAreaStore $focusAreaStore
	 * @param LoggerInterface $logger
	 */
	public function __construct( WishlistConfig $config, FocusAreaStore $focusAreaStore, LoggerInterface $logger ) {
		parent::__construct( $config, $logger );
		$this->focusAreaStore = $focusAreaStore;
	}

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ): void {
		if ( !$this->config->isEnabled() ) {
			return;
		}

		$parser->setHook( 'focus-area', [ $this, 'renderFocusArea' ] );
	}

	/**
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public function renderFocusArea( $input, array $args, Parser $parser, PPFrame $frame ): string {
		if ( !$this->config->isEnabled() ) {
			return '';
		}

		$data = [
			'shortDescription' => $args['shortdescription'] ?? '',
			'title' => $args['title'] ?? '',
			'status' => $args['status'] ?? '',
		];

		$this->logger->debug( __METHOD__ . ": Rendering focus area. {0}", [ json_encode( $data ) ] );
		$parser->getOutput()->setExtensionData( self::EXT_DATA_FOCUS_AREA_KEY, $data );

		return '';
	}

	/** @inheritDoc */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) {
		if ( !$this->config->isEnabled() ) {
			return;
		}

		$data = $linksUpdate->getParserOutput()->getExtensionData( self::EXT_DATA_FOCUS_AREA_KEY );
		$page = $linksUpdate->getTitle();
		$language = $page->getPageLanguage()->getCode();

		// only save focus area data if being used on within the config prefix
		if ( !$this->focusAreaStore->isFocusAreaPage( $page ) ) {
			$this->logger->debug( __METHOD__ . ": Not a focus area page." );
			return;
		}

		$data['status'] = $this->config->getStatusIdFromWikitextVal( $data['status'] ?? '' );

		// TODO: handle subpage created by Translate extension with language code equal to the base page
		// language code in a follow-up task
		try {
			$focusArea = new FocusArea( $page, $language, $data );
			$this->focusAreaStore->save( $focusArea );
		} catch ( \InvalidArgumentException $e ) {
			$this->logger->error( __METHOD__ . ": {0}", [ $e->getMessage() ] );
		}
	}

	/** @inheritDoc */
	public function onPageDeleteComplete(
		ProperPageIdentity $page,
		Authority $deleter,
		string $reason,
		int $pageID,
		RevisionRecord $deletedRev,
		ManualLogEntry $logEntry,
		int $archivedRevisionCount
	) {
		if ( !$this->config->isEnabled() ) {
			return;
		}

		if ( !$this->focusAreaStore->isFocusAreaPage( $page ) ) {
			$this->logger->debug( __METHOD__ . ": Not a focus area page." );
			return;
		}

		// TODO: implement deletion logic in follow-up task
	}
}
