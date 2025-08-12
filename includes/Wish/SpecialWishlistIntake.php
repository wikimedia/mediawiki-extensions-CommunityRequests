<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistSpecialPage;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Message\Message;
use MediaWiki\Title\TitleParser;
use MediaWiki\User\UserFactory;

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
		protected UserFactory $userFactory
	) {
		parent::__construct(
			$config,
			$wishStore,
			$titleParser,
			'WishlistIntake',
		);
	}

	/** @inheritDoc */
	public function getDescription(): Message {
		return $this->msg( 'communityrequests-wishlistintake' );
	}

	/** @inheritDoc */
	public function execute( $entityId ): bool {
		parent::execute( (string)$entityId );

		$this->getOutput()->setSubtitle( $this->msg( 'communityrequests-form-subtitle' ) );

		// Fetch focus area titles and wikitext values.
		/** @var FocusArea[] $focusAreas */
		$focusAreas = $this->focusAreaStore->getAll(
			$this->getLanguage()->getCode(),
			FocusAreaStore::titleField(),
			FocusAreaStore::SORT_ASC,
			// TODO: Scalability/performance; 1000 should last us, forever… but we should not hardcode this.
			//   Maybe replace with an PrefixIndex query?
			1000
		);
		$focusAreaData = [];
		foreach ( $focusAreas as $focusArea ) {
			$wikitextVal = $this->config->getEntityWikitextVal( $focusArea->getPage() );
			$focusAreaData[(string)$wikitextVal] = $focusArea->getTitle();
		}

		// Add to JS config.
		$this->getOutput()->addJsConfigVars( [
			'intakeFocusAreas' => $focusAreaData,
			'intakeAudienceMaxChars' => WishStore::AUDIENCE_MAX_CHARS,
		] );

		return true;
	}

	/** @inheritDoc */
	protected function showErrorPage(): void {
		$this->getOutput()->showErrorPage(
			'communityrequests-wishlistintake',
			'communityrequests-wish-not-found',
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
	public function onSubmit( array $data, ?HTMLForm $form = null ) {
		// Grab data directly from POST request. We should use the given $data once ::getFormFields() is implemented.
		$data = $form->getRequest()->getPostValues();
		$data['title'] = $data['entitytitle'];

		// API wants pipe-separated arrays, not CSV.
		$data['projects'] = str_replace( ',', '|', $data['projects'] );
		$data['phabtasks'] = str_replace( ',', '|', $data['phabtasks'] );

		return parent::onSubmit( $data, $form );
	}

	/** @inheritDoc */
	protected function getApiPath(): string {
		return 'wish';
	}
}
