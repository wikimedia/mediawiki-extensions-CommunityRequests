<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\FocusArea;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistSpecialPage;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use Psr\Log\LoggerInterface;

class SpecialEditFocusArea extends AbstractWishlistSpecialPage {

	/** @inheritDoc */
	public function __construct(
		protected WishlistConfig $config,
		protected FocusAreaStore $focusAreaStore,
		protected TitleParser $titleParser,
		protected LoggerInterface $logger,
	) {
		parent::__construct(
			$config,
			$focusAreaStore,
			$titleParser,
			$logger,
			'EditFocusArea',
			'manage-wishlist',
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
	public function onSubmit( array $data, ?HTMLForm $form = null ) {
		$status = parent::onSubmit( $data, $form );

		// Invalidate the cache for the focus area index page.
		if ( $status->isOK() ) {
			Title::newFromText( $this->config->getFocusAreaIndexPage() )->invalidateCache();
		}

		return $status;
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
