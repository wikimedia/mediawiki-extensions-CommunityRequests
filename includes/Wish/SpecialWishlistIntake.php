<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistSpecialPage;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use MediaWiki\User\UserFactory;
use Psr\Log\LoggerInterface;

/**
 * JS-only Special page for submitting a new community request.
 */
class SpecialWishlistIntake extends AbstractWishlistSpecialPage {

	/** @inheritDoc */
	public function __construct(
		protected WishlistConfig $config,
		protected WishStore $wishStore,
		protected FocusAreaStore $focusAreaStore,
		protected TitleParser $titleParser,
		protected UserFactory $userFactory,
		protected LoggerInterface $logger,
	) {
		parent::__construct(
			$config,
			$wishStore,
			$titleParser,
			$logger,
			'WishlistIntake',
		);
	}

	/** @inheritDoc */
	public function getDescription(): Message {
		return $this->msg( 'communityrequests-wishlistintake' );
	}

	/** @inheritDoc */
	public function execute( $entityId ) {
		parent::execute( (string)$entityId );
		if ( !$this->config->isEnabled() ) {
			return;
		}

		$this->getOutput()->setSubtitle( $this->msg( 'communityrequests-form-subtitle' ) );

		$focusAreaData = $this->focusAreaStore->getTitlesByEntityWikitextVal(
			$this->getLanguage()->getCode(),
		);

		// Add to JS config.
		$this->getOutput()->addJsConfigVars( [
			'intakeFocusAreas' => $focusAreaData,
			'intakeAudienceMaxChars' => WishStore::AUDIENCE_MAX_CHARS,
		] );
	}

	/** @inheritDoc */
	protected function showErrorPage( Title $title ): void {
		$this->getOutput()->showErrorPage(
			'communityrequests-wishlistintake',
			'communityrequests-wish-not-found',
			[ $title->getPrefixedText(), $title->getSubpageText() ],
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
		return 'wish';
	}
}
