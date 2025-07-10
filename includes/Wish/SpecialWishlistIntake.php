<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistSpecialPage;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Message\Message;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
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
			$focusAreaData[ (string)$wikitextVal ] = $focusArea->getTitle();
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
		$data[ 'title' ] = $data[ 'entitytitle' ];

		// API wants pipe-separated arrays, not CSV.
		$data[ 'projects' ] = str_replace( ',', '|', $data[ 'projects' ] );
		$data[ 'phabtasks' ] = str_replace( ',', '|', $data[ 'phabtasks' ] );

		$context = new DerivativeContext( $this->getContext() );
		$context->setRequest( new DerivativeRequest( $this->getRequest(), [
			'action' => 'wishedit',
			'wish' => $this->entityId,
			'token' => $data[ 'wpEditToken' ],
			...$data,
		] ) );
		$api = new ApiMain( $context, true );
		try {
			$api->execute();
		} catch ( ApiUsageException $e ) {
			return $e->getStatusValue();
		}

		$this->pageTitle = Title::newFromText( $api->getResult()->getResultData()[ 'wishedit' ][ 'wish' ] );

		// Set session variables to show post-edit messages.
		$this->getRequest()->getSession()->set(
			CommunityRequestsHooks::SESSION_KEY,
			$this->entityId === null ? self::SESSION_VALUE_CREATED : self::SESSION_VALUE_UPDATED
		);
		// Redirect to wish page.
		$this->getOutput()->redirect( $this->pageTitle->getFullURL() );

		return Status::newGood( $api->getResult() );
	}
}
