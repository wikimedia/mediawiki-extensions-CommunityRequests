<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Hook\RecentChange_saveHook;
use MediaWiki\RecentChanges\RecentChange;

class ChangeTagHooks implements ChangeTagsListActiveHook, ListDefinedTagsHook, RecentChange_saveHook {

	public const WISHLIST_CHANGE_TAG = 'community-wishlist';

	public function __construct( protected readonly WishlistConfig $config ) {
	}

	/** @inheritDoc */
	public function onListDefinedTags( &$tags ) {
		if ( $this->config->isEnabled() ) {
			$tags[] = self::WISHLIST_CHANGE_TAG;
		}
	}

	/** @inheritDoc */
	public function onChangeTagsListActive( &$tags ) {
		if ( $this->config->isEnabled() ) {
			$tags[] = self::WISHLIST_CHANGE_TAG;
		}
	}

	/**
	 * Adds the self::WISHLIST_CHANGE_TAG tag to recent changes
	 * if the request was made using SpecialWishlistIntake.
	 *
	 * @param RecentChange $rc
	 */
	public function onRecentChange_save( $rc ) {
		$request = RequestContext::getMain()->getRequest();
		if ( $request->getSession()->get( CommunityRequestsHooks::SESSION_KEY ) ) {
			$rc->addTags( self::WISHLIST_CHANGE_TAG );
		}
	}
}
