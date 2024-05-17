<?php

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Output\Hook\BeforePageDisplayHook;

class Hooks implements BeforePageDisplayHook {

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * @param \OutputPage $out
	 * @param \Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
	}

}
