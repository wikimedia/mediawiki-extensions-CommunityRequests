<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Content\WikitextContent;
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
use MediaWiki\User\UserFactory;

/**
 * JS-only Special page for submitting a new community request.
 */
class SpecialWishlistIntake extends FormSpecialPage {

	public const SESSION_KEY = 'communityrequests-intake';
	public const SESSION_VALUE_WISH_CREATED = 'created';
	public const SESSION_VALUE_WISH_UPDATED = 'updated';
	protected const EDIT_SUMMARY_PUBLISH = 'communityrequests-publish-wish-summary';
	protected const EDIT_SUMMARY_SAVE = 'communityrequests-save-wish-summary';

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

		// Permit the full page title to be passed instead of just the ID.
		$wishId = ltrim( $wishId ?? '', $this->config->getWishPagePrefix() );

		// Extract the integer from the $wishId, which may be a string like "W123".
		$wishId = preg_replace( '/[^0-9]/', '', $wishId ) ?: null;

		if ( $wishId ) {
			$ret = $this->loadExistingWish( (int)$wishId );
			if ( !$ret ) {
				return;
			}
		}

		$this->getOutput()->addModules( 'ext.communityrequests.intake' );

		// For VisualEditor.
		// TODO: Remove hard dependency on VE
		$this->getOutput()->addJsConfigVars( 'intakeVeModules', $this->preloadVeModules() );

		$this->getOutput()->setSubtitle( $this->msg( 'communityrequests-form-subtitle' ) );

		parent::execute( $wishId );
	}

	/**
	 * Load an existing wish by its ID and prepare the data for the Vue app.
	 *
	 * @param int $wishId
	 * @return bool False if there was an error.
	 */
	private function loadExistingWish( int $wishId ): bool {
		$this->pageTitle = Title::newFromText(
			$this->config->getWishPagePrefix() . $wishId
		);
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
		$form->setId( 'ext-communityrequests-intake-form' )
			->suppressDefaultSubmit()
			// Add div that the Vue app will be mounted to. This needs to be inside the server-generated <form> tag.
			->addHeaderHtml( Html::element( 'div', [ 'class' => 'ext-communityrequests-intake' ] ) );
	}

	/** @inheritDoc */
	public function onSubmit( array $data, ?HTMLForm $form = null ) {
		// Grab data directly from POST request. We should use the given $data once ::getFormFields() is implemented.
		$data = $form->getRequest()->getPostValues();
		$data[ 'title' ] = $data[ 'wishtitle' ];

		if ( $this->wishId === null ) {
			// If this is a new wish, generate a new ID and page title.
			$id = $this->wishStore->getNewId();
			$this->pageTitle = Title::newFromText(
				$this->config->getWishPagePrefix() . $id
			);
		}

		$wish = Wish::newFromWikitextParams(
			$this->pageTitle,
			$this->getContentLanguage()->getCode(),
			$this->userFactory->newFromName( $data[ 'proposer' ] ),
			$data,
			$this->config
		);

		$wishTemplate = $this->titleParser->parseTitle( $this->config->getWishTemplatePage() );

		// Generate edit summary.
		$summary = $this->msg(
			$this->wishId === null ? self::EDIT_SUMMARY_PUBLISH : self::EDIT_SUMMARY_SAVE,
			$data[ 'title' ]
		)->text();

		// If there are Phabricator tasks, add them to the edit summary.
		if ( count( $wish->getPhabTasks() ) > 0 ) {
			$taskLinks = array_map(
				static fn ( int $taskId ) => "[[phab:T{$taskId}|T{$taskId}]]",
				$wish->getPhabTasks()
			);
			$summary .= ' ' .
				$this->msg( 'parentheses-start' )->text() .
				$this->getLanguage()->commaList( $taskLinks ) .
				$this->msg( 'parentheses-end' )->text();
		}

		$status = $this->save(
			$wish->toWikitext( $wishTemplate, $this->config ),
			$summary,
			$data[ 'wpEditToken' ],
			(int)$data[ 'baserevid' ] ?: null,
			// FIXME: use Wish::WISHLIST_TAG once we have ApiWishEdit
			// ApiEditPage doesn't allow for software-defined tags.
			[]
		);

		if ( $status->isOK() ) {
			// Set session variables to show post-edit messages.
			$this->getRequest()->getSession()->set(
				self::SESSION_KEY,
				$this->wishId === null ? self::SESSION_VALUE_WISH_CREATED : self::SESSION_VALUE_WISH_UPDATED
			);
			// Redirect to wish page.
			$this->getOutput()->redirect( $this->pageTitle->getFullURL() );
		}

		return $status;
	}

	/**
	 * Save the wish content to the wiki page.
	 * WishHookHandler will handle updating the CommunityRequests tables.
	 *
	 * @param WikitextContent $content
	 * @param string $summary
	 * @param string $token
	 * @param ?int $baseRevId
	 * @param string[] $tags
	 * @return Status
	 * @todo move to ApiWishEdit
	 */
	private function save(
		WikitextContent $content,
		string $summary,
		string $token,
		?int $baseRevId = null,
		array $tags = []
	): Status {
		$apiParams = [
			'action' => 'edit',
			'title' => $this->pageTitle->getPrefixedDBkey(),
			'text' => $content->getText(),
			'summary' => $summary,
			'token' => $token,
			'baserevid' => $baseRevId,
			'tags' => implode( '|', $tags ),
			'errorformat' => 'html',
			'notminor' => true,
		];

		$context = new DerivativeContext( $this->getContext() );
		$context->setRequest( new DerivativeRequest( $this->getRequest(), $apiParams ) );
		$api = new ApiMain( $context, true );

		// FIXME: make use of EditFilterMergedContent hook to impose our own edit checks
		//   (Status will show up in SpecialFormPage) Such as a missing proposer or invalid creation date.
		try {
			$api->execute();
		} catch ( ApiUsageException $e ) {
			return Status::wrap( $e->getStatusValue() );
		}

		return Status::newGood( $api->getResult() );
	}
}
