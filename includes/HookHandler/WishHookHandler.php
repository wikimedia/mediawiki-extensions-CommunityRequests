<?php

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserFactory;

/**
 * Hook handlers for the <wish> tag.
 */
class WishHookHandler extends CommunityRequestsHooks implements
	ChangeTagsListActiveHook,
	LinksUpdateCompleteHook,
	ListDefinedTagsHook,
	PageDeleteCompleteHook,
	ParserFirstCallInitHook,
	BeforePageDisplayHook
{
	public const EXT_DATA_WISH_KEY = 'ext-communityrequests-wish';
	public const SESSION_KEY = 'communityrequests-intake';
	private WishStore $wishStore;
	private UserFactory $userFactory;

	public function __construct( Config $config, WishStore $wishStore, UserFactory $userFactory ) {
		parent::__construct( $config );
		$this->wishStore = $wishStore;
		$this->userFactory = $userFactory;
	}

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ) {
		if ( !$this->enabled ) {
			return;
		}
		$parser->setHook( 'wish', [ $this, 'renderWish' ] );
	}

	// Editing wishes

	/**
	 * Render the <wish> tag.
	 *
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @return string
	 */
	public function renderWish( $input, array $args, Parser $parser ): string {
		if ( !$this->enabled ) {
			return '';
		}
		$args[ 'lang' ] = $parser->getContentLanguage()->getCode();

		// Add tracking category for missing data.
		$requiredFields = [ 'title', 'proposer', 'created' ];
		foreach ( $requiredFields as $field ) {
			if ( !isset( $args[ $field ] ) || trim( $args[ $field ] ) === '' ) {
				$parser->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
				break;
			}
		}

		// Cache the wish data for storage after the links update.
		$parser->getOutput()->setExtensionData( self::EXT_DATA_WISH_KEY, $args );

		// The <wish> tag should have no output.
		return '';
	}

	/** @inheritDoc */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) {
		if ( !$this->enabled ) {
			return;
		}
		$data = $linksUpdate->getParserOutput()->getExtensionData( self::EXT_DATA_WISH_KEY );
		if ( !$data ) {
			return;
		}

		$proposer = $this->userFactory->newFromName( $data[ 'proposer' ] ?? '' );
		if ( !$proposer ) {
			$proposer = $linksUpdate->getRevisionRecord()->getUser();
		}

		$wish = Wish::newFromWikitextParams(
			$linksUpdate->getTitle(),
			$data[ 'lang' ],
			$proposer,
			$data,
			$this->config
		);
		$this->wishStore->save( $wish );
	}

	// Viewing wishes

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->enabled || !$this->wishStore->isWishPage( $out->getTitle() ) ) {
			return;
		}

		if ( $out->getRequest()->getSession()->get( self::SESSION_KEY ) ) {
			$out->getRequest()->getSession()->remove( self::SESSION_KEY );
			$out->addJsConfigVars(
				'intakePostEdit',
				$out->getRequest()->getSession()->get( self::SESSION_KEY )
			);
			$out->addModules( 'ext.communityrequests.intake' );
		}
	}

	// Deleting wishes

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

	// Change tags

	/** @inheritDoc */
	public function onListDefinedTags( &$tags ) {
		if ( $this->enabled ) {
			$tags[] = Wish::WISHLIST_TAG;
		}
	}

	/** @inheritDoc */
	public function onChangeTagsListActive( &$tags ) {
		if ( $this->enabled ) {
			$tags[] = Wish::WISHLIST_TAG;
		}
	}
}
