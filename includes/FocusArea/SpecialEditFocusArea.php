<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\FocusArea;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistSpecialPage;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Message\Message;
use MediaWiki\Title\TitleParser;

class SpecialEditFocusArea extends AbstractWishlistSpecialPage {

	/** @inheritDoc */
	public function __construct(
		protected WishlistConfig $config,
		protected FocusAreaStore $focusAreaStore,
		protected TitleParser $titleParser
	) {
		parent::__construct(
			$config,
			$focusAreaStore,
			$titleParser,
			'EditFocusArea',
			'manage-wishlist'
		);
	}

	/** @inheritDoc */
	public function getDescription(): Message {
		return $this->msg( 'communityrequests-editfocusarea' );
	}

	/** @inheritDoc */
	public function isRestricted(): bool {
		return true;
	}

	/** @inheritDoc */
	protected function showErrorPage(): void {
		$this->getOutput()->showErrorPage(
			'communityrequests-editfocusarea',
			'communityrequests-focus-area-not-found',
			[ $this->pageTitle->getPrefixedText() ],
			$this->config->getHomepage()
		);
	}

	/** @inheritDoc */
	protected function getFormFields(): array {
		// FIXME: Use form descriptor and leverage FormSpecialPage once Codex PHP is ready (T379662)
		return [];
	}

	/** @inheritDoc */
	protected function getApiPath(): string {
		return 'focusarea';
	}
}
