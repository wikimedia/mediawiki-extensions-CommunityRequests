<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\SpecialPage\RedirectSpecialPage;
use MediaWiki\Title\Title;

/**
 * Special page for redirecting to the wishlist homepage or a specific entity.
 */
class SpecialWishlist extends RedirectSpecialPage {

	public function __construct( private WishlistConfig $config ) {
		parent::__construct( 'Wishlist' );
	}

	/** @inheritDoc */
	public function getRedirect( $subpage ): Title {
		$title = Title::newFromText( $this->config->getHomepage() );
		if ( $subpage ) {
			$subpageRef = $this->config->getEntityPageRefFromWikitextVal( $subpage );
			return $subpageRef ? Title::newFromPageReference( $subpageRef ) : $title;
		}
		return $title;
	}
}
