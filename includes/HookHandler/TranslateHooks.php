<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\Translate\PageTranslation\TranslateTitleEnum;
use MediaWiki\Extension\Translate\PageTranslation\TranslateTitlePageTranslationHook;
use MediaWiki\Page\PageIdentity;

class TranslateHooks implements TranslateTitlePageTranslationHook {

	public function __construct(
		private readonly WishlistConfig $config,
	) {
	}

	/** @inheritDoc */
	public function onTranslateTitlePageTranslation( TranslateTitleEnum &$state, PageIdentity $pageIdentity ): void {
		if ( $this->config->isEnabled() && $this->config->isWishOrFocusAreaPage( $pageIdentity ) ) {
			$state = TranslateTitleEnum::DISABLED;
		}
	}
}
