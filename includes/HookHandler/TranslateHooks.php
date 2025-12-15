<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\Translate\PageTranslation\TranslateTitleEnum;
use MediaWiki\Extension\Translate\PageTranslation\TranslateTitlePageTranslationHook;
use MediaWiki\Page\PageIdentity;

class TranslateHooks implements TranslateTitlePageTranslationHook {

	public function __construct(
		protected readonly WishlistConfig $config,
	) {
	}

	/** @inheritDoc */
	public function onTranslateTitlePageTranslation(
		TranslateTitleEnum &$state,
		PageIdentity $page,
		?string &$reason
	): void {
		if ( $this->config->isEnabled() && $this->config->isEntityPage( $page ) ) {
			$state = TranslateTitleEnum::DISABLED;
			$reason = wfMessage( 'communityrequests-translate-title-disabled-reason' )->escaped();
		}
	}
}
