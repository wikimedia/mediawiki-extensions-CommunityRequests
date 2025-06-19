<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Api\ApiMain;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserFactory;

/**
 * JS-only Special page for submitting a new community request.
 */
class SpecialWishlistIntake extends FormSpecialPage {

	public const SESSION_KEY = 'communityrequests-intake';
	public const SESSION_VALUE_WISH_CREATED = 'created';
	public const SESSION_VALUE_WISH_UPDATED = 'updated';

	private Title $pageTitle;
	protected ?int $wishId = null;

	/** @inheritDoc */
	public function __construct(
		protected WishlistConfig $config,
		protected WishStore $wishStore,
		protected $wikiPageFactory,
		protected TitleParser $titleParser,
		protected HookContainer $hookContainer,
		protected UserFactory $userFactory
	) {
		parent::__construct( 'WishlistIntake' );
	}

	/** @inheritDoc */
	public function getDescription(): Message {
		return $this->msg( 'communityrequests-wishlistintake' );
	}

	/** @inheritDoc */
	public function isListed(): bool {
		return parent::isListed() && $this->config->isEnabled();
	}

	/** @inheritDoc */
	public function execute( $wishId ): void {
		if ( !$this->config->isEnabled() ) {
			$this->setHeaders();
			$this->getOutput()->addWikiMsg( 'communityrequests-disabled' );
			return;
		}

		$this->requireNamedUser( 'communityrequests-please-log-in' );

		$wishId = $this->wishStore->getWishIdFromInput( $wishId );
		if ( $wishId ) {
			$ret = $this->loadExistingWish( $wishId );
			if ( !$ret ) {
				return;
			}
		}

		$this->getOutput()->addModules( 'ext.communityrequests.intake' );

		// For VisualEditor.
		// TODO: Remove hard dependency on VE
		$this->getOutput()->addJsConfigVars( 'intakeVeModules', $this->preloadVeModules() );

		$this->getOutput()->setSubtitle( $this->msg( 'communityrequests-form-subtitle' ) );

		$this->getOutput()->addJsConfigVars( [
			'intakeTitleMaxChars' => WishStore::TITLE_MAX_CHARS,
			'intakeAudienceMaxChars' => WishStore::AUDIENCE_MAX_CHARS,
		] );

		parent::execute( (string)$wishId );
	}

	/**
	 * Load an existing wish by its ID and prepare the data for the Vue app.
	 *
	 * @param int $wishId
	 * @return bool False if there was an error.
	 */
	private function loadExistingWish( int $wishId ): bool {
		$this->pageTitle = Title::newFromText( $this->config->getWishPagePrefix() . $wishId );
		$wish = $this->wishStore->getWish( $this->pageTitle );

		if ( !$wish ) {
			$this->getOutput()->showErrorPage(
				'communityrequests-wishlistintake',
				'communityrequests-wish-not-found',
				[ $this->pageTitle->getPrefixedText() ],
				$this->config->getHomepage()
			);
			return false;
		}

		$wikitextData = $this->wishStore->getDataFromWikitext( $wish );
		'@phan-var array $wikitextData';
		$templateParams = $this->config->getWishTemplateParams();
		$this->getOutput()->addJsConfigVars( [
			'intakeWishId' => $wishId,
			'intakeWishData' => [
				...$wish->toArray( $this->config ),
				'description' => $wikitextData[ $templateParams[ 'description' ] ],
				'audience' => $wikitextData[ $templateParams[ 'audience' ] ],
				'baseRevId' => $wikitextData[ 'baseRevId' ],
			],
		] );

		$this->wishId = $wishId;

		return true;
	}

	/**
	 * Add configurable messages to the ResourceLoader module.
	 *
	 * @param array $moduleConfig
	 * @return RL\Module
	 */
	public static function addResourceLoaderMessages( array $moduleConfig ): RL\Module {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$messages = [];
		foreach ( $config->get( 'CommunityRequestsWishTypes' ) as $type ) {
			$messages[] = $type['label'] . '-label';
			$messages[] = $type['label'] . '-description';
		}
		foreach ( $config->get( 'CommunityRequestsProjects' ) as $project ) {
			$messages[] = $project['label'];
		}
		foreach ( $config->get( 'CommunityRequestsStatuses' ) as $status ) {
			$messages[] = $status['label'];
		}

		$moduleConfig['messages'] = array_merge( $moduleConfig['messages'], $messages );
		$class = $moduleConfig['class'] ?? RL\FileModule::class;
		return new $class( $moduleConfig );
	}

	/**
	 * Get and preload VE modules depending on the skin and loaded extensions.
	 *
	 * @return string[]
	 */
	private function preloadVeModules(): array {
		$modules = [
			$this->getSkin()->getSkinName() === 'minerva' ?
				'ext.visualEditor.core.mobile' :
				'ext.visualEditor.core.desktop',
			'ext.visualEditor.mwcore',
			'ext.visualEditor.mwwikitext',
			'ext.visualEditor.switching',
			'ext.visualEditor.desktopTarget',
			'ext.visualEditor.mwextensions',
			'oojs-ui-widgets',
			'oojs-ui.styles.indicators',
			'oojs-ui.styles.icons-editing-styling',
			'oojs-ui.styles.icons-editing-list',
			'mediawiki.ForeignStructuredUpload.BookletLayout'
		];
		$extRegistry = ExtensionRegistry::getInstance();
		if ( $extRegistry->isLoaded( 'Cite' ) ) {
			$modules[] = 'ext.cite.visualEditor';
		}
		if ( $extRegistry->isLoaded( 'Citoid' ) ) {
			$modules[] = 'ext.citoid.visualEditor';
		}
		if ( $extRegistry->isLoaded( 'Translate' ) ) {
			$modules[] = 'ext.translate.ve';
		}
		$this->getOutput()->addModules( $modules );
		return $modules;
	}

	/** @inheritDoc */
	protected function getDisplayFormat() {
		return 'codex';
	}

	/** @inheritDoc */
	protected function getFormFields(): array {
		// FIXME: Use form descriptor and leverage FormSpecialPage once Codex PHP is ready (T379662)
		return [];
	}

	/** @inheritDoc */
	protected function alterForm( HTMLForm $form, string $module = 'ext.communityrequests.intake' ) {
		// Loading state and fallback for no-JS users. (To be removed once we have Codex PHP.)
		$loadingText = Html::element( 'p', [], $this->msg( 'communityrequests-form-loading' )->text() );
		$homepage = $this->titleParser->parseTitle( $this->config->getHomepage() );
		$discussionPage = new TitleValue( $homepage->getNamespace() + 1, $homepage->getDBkey() );
		$loadingText2 = Html::rawElement( 'p', [],
			$this->msg( 'communityrequests-form-loading-1' ) . "\n" .
			$this->msg(
				'communityrequests-form-loading-2',
				$this->getLinkRenderer()->makeLink( $discussionPage )
			)->plain()
		);
		$container = Html::rawElement( 'div', [
			'class' => 'ext-communityrequests-intake',
		], $loadingText . $loadingText2 );

		$form->setId( 'ext-communityrequests-intake-form' )
			->suppressDefaultSubmit()
			// Add div that the Vue app will be mounted to. This needs to be inside the server-generated <form> tag.
			->addHeaderHtml( $container );
	}

	/** @inheritDoc */
	public function onSubmit( array $data, ?HTMLForm $form = null ) {
		// Grab data directly from POST request. We should use the given $data once ::getFormFields() is implemented.
		$data = $form->getRequest()->getPostValues();
		$data[ 'title' ] = $data[ 'wishtitle' ];

		// API wants pipe-separated arrays, not CSV.
		$data[ 'projects' ] = str_replace( ',', '|', $data[ 'projects' ] );
		$data[ 'phabtasks' ] = str_replace( ',', '|', $data[ 'phabtasks' ] );

		$context = new DerivativeContext( $this->getContext() );
		$context->setRequest( new DerivativeRequest( $this->getRequest(), [
			'action' => 'wishedit',
			'wish' => $this->wishId,
			'token' => $data[ 'wpEditToken' ],
			...$data,
		] ) );
		$api = new ApiMain( $context, true );
		$api->execute();

		$this->pageTitle = Title::newFromText( $api->getResult()->getResultData()[ 'wishedit' ][ 'wish' ] );

		// Set session variables to show post-edit messages.
		$this->getRequest()->getSession()->set(
			self::SESSION_KEY,
			$this->wishId === null ? self::SESSION_VALUE_WISH_CREATED : self::SESSION_VALUE_WISH_UPDATED
		);
		// Redirect to wish page.
		$this->getOutput()->redirect( $this->pageTitle->getFullURL() );

		return Status::newGood( $api->getResult() );
	}
}
