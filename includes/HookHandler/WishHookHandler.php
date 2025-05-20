<?php

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;

/**
 * Hook handlers for the <wish> tag.
 */
class WishHookHandler extends CommunityRequestsHooks implements
	ParserFirstCallInitHook,
	LinksUpdateCompleteHook,
	PageDeleteCompleteHook
{
	public const EXT_DATA_WISH_KEY = 'ext-communityrequests-wish';
	private WishStore $wishStore;

	public function __construct( Config $config, WishStore $wishStore ) {
		parent::__construct( $config );
		$this->wishStore = $wishStore;
	}

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ) {
		if ( !$this->enabled ) {
			return;
		}
		$parser->setHook( 'wish', [ $this, 'renderWish' ] );
	}

	/**
	 * Render the <wish> tag.
	 *
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @return string
	 */
	public function renderWish( $input, array $args, Parser $parser ): string {
		$args['page'] = $parser->getPage();
		$args['lang'] = $parser->getContentLanguage()->getCode();
		$args['user'] = $parser->getUserIdentity();
		$args['projects'] = array_map( 'intval', explode( ',', $args['projects'] ?? '' ) );
		$args['phabTasks'] = array_map( 'intval', explode( ',', $args['phabTasks'] ?? '' ) );
		$parser->getOutput()->setExtensionData( self::EXT_DATA_WISH_KEY, $args );
		// The <wish> tag should have no output.
		return '';
	}

	/** @inheritDoc */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) {
		$data = $linksUpdate->getParserOutput()->getExtensionData( self::EXT_DATA_WISH_KEY );
		if ( !$data ) {
			return;
		}
		$wish = new Wish( $data['page'], $data['lang'], $data['user'], $data );
		$this->wishStore->save( $wish );
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
		if ( !$this->enabled ) {
			return;
		}
		$wish = $this->wishStore->getWish( $page );
		if ( $wish ) {
			$this->wishStore->delete( $wish );
		}
	}
}
