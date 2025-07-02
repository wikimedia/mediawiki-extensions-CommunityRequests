<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\FocusArea;

use MediaWiki\Api\ApiMain;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistSpecialPage;
use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Message\Message;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
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
	protected function getFormFields(): array {
		// FIXME: Use form descriptor and leverage FormSpecialPage once Codex PHP is ready (T379662)
		return [];
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
		// Grab data directly from POST request. We should use the given $data once ::getFormFields() is implemented.
		$data = $form->getRequest()->getPostValues();
		$data[ 'title' ] = $data[ 'focusareatitle' ];

		$context = new DerivativeContext( $this->getContext() );
		$context->setRequest( new DerivativeRequest( $this->getRequest(), [
			'action' => 'focusareaedit',
			'focusarea' => $this->entityId,
			'token' => $data[ 'wpEditToken' ],
			...$data,
		] ) );
		$api = new ApiMain( $context, true );
		$api->execute();

		$this->pageTitle = Title::newFromText( $api->getResult()->getResultData()[ 'focusareaedit' ][ 'focusarea' ] );

		// Set session variables to show post-edit messages.
		$this->getRequest()->getSession()->set(
			CommunityRequestsHooks::SESSION_KEY,
			$this->entityId === null ? self::SESSION_VALUE_CREATED : self::SESSION_VALUE_UPDATED
		);
		// Redirect to focus area page.
		$this->getOutput()->redirect( $this->pageTitle->getFullURL() );

		return Status::newGood( $api->getResult() );
	}
}
