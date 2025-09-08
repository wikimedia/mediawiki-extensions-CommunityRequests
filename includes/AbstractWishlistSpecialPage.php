<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use Psr\Log\LoggerInterface;

abstract class AbstractWishlistSpecialPage extends FormSpecialPage {

	public const SESSION_VALUE_CREATED = 'created';
	public const SESSION_VALUE_UPDATED = 'updated';

	protected Title $pageTitle;
	protected ?int $entityId = null;

	/** @inheritDoc */
	public function __construct(
		protected WishlistConfig $config,
		protected AbstractWishlistStore $store,
		protected TitleParser $titleParser,
		protected LoggerInterface $logger,
		string $name,
		string $restriction = ''
	) {
		parent::__construct( $name, $restriction );
	}

	/** @inheritDoc */
	public function execute( $entityId ) {
		if ( !$this->config->isEnabled() ) {
			$this->setHeaders();
			$this->getOutput()->addWikiMsg( 'communityrequests-disabled' );
			return;
		}
		$this->requireNamedUser( 'communityrequests-please-log-in' );

		$this->entityId = $this->store->getIdFromInput( $entityId );
		$this->pageTitle = Title::newFromText( $this->store->getPagePrefix() . $this->entityId );

		// Redirect to "view source" (action=edit) if the user does not have permission to edit.
		if ( !$this->getUser()->probablyCan( 'edit', $this->pageTitle ) ) {
			$this->getOutput()->redirect( $this->pageTitle->getEditURL() );
		}

		if ( $this->entityId && !$this->loadExistingEntity( $this->entityId ) ) {
			return;
		}

		$this->getOutput()->addModules( 'ext.communityrequests.intake' );

		// For VisualEditor.
		// TODO: Remove hard dependency on VE
		$this->getOutput()->addJsConfigVars( 'intakeVeModules', $this->preloadVeModules() );

		$this->getOutput()->addJsConfigVars( [
			'intakeTitleMaxChars' => AbstractWishlistStore::TITLE_MAX_CHARS,
			'intakeWishlistManager' => $this->getUser()->isAllowed( 'manage-wishlist' ),
			'copyrightWarning' => EditPage::getCopyrightWarning( $this->getFullTitle(), 'parse', $this->getContext() ),
		] );

		parent::execute( (string)$entityId );
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
		foreach ( $config->get( 'CommunityRequestsTags' ) as $tagGroup ) {
			foreach ( $tagGroup as $tag => $tagConfig ) {
				$messages[] = $tagConfig['label'] ?? "communityrequests-tag-$tag";
			}
		}
		foreach ( $config->get( 'CommunityRequestsStatuses' ) as $status ) {
			$messages[] = $status['label'];
		}

		$moduleConfig['messages'] = array_merge( $moduleConfig['messages'], $messages );
		$class = $moduleConfig['class'] ?? RL\FileModule::class;
		return new $class( $moduleConfig );
	}

	/**
	 * Load an existing entity (wish or focus area) from the store.
	 *
	 * @param int $entityId
	 * @param ?Title $title Ignore; for unit tests only.
	 * @return bool True if the entity was found and loaded, false otherwise.
	 */
	public function loadExistingEntity( int $entityId, ?Title $title = null ): bool {
		$entity = $this->store->get(
			$title ?? $this->pageTitle,
			null,
			AbstractWishlistStore::FETCH_WIKITEXT_RAW,
		);

		if ( !$entity ) {
			$this->showErrorPage();
			return false;
		}

		$this->getOutput()->addJsConfigVars( [
			'intakeId' => $entityId,
			'intakeData' => $entity->toArray( $this->config ),
		] );

		$this->entityId = $entityId;

		return true;
	}

	/** @inheritDoc */
	protected function alterForm( HTMLForm $form ) {
		// Loading state and fallback for no-JS users. (To be removed once we have Codex PHP.)
		$loadingText = Html::element( 'p', [], $this->msg( 'communityrequests-form-loading' )->text() );
		$homepage = $this->titleParser->parseTitle( $this->config->getHomepage() );
		$discussionPage = new TitleValue( $homepage->getNamespace() + 1, $homepage->getDBkey() );
		$loadingText2 = Html::rawElement( 'p', [],
			$this->msg( 'communityrequests-form-loading-1' ) . "\n" .
			$this->msg( 'communityrequests-form-loading-2' )
				->rawParams( $this->getLinkRenderer()->makeLink( $discussionPage ) )
				->escaped()
		);
		$container = Html::rawElement( 'div', [
			'class' => 'ext-communityrequests-intake',
		], $loadingText . $loadingText2 );

		$form->setId( 'ext-communityrequests-intake-form' )
			->suppressDefaultSubmit()
			// Add div that the Vue app will be mounted to. This needs to be inside the server-generated <form> tag.
			->addHeaderHtml( $container );
	}

	/**
	 * Error page to show when the entity (wish or focus area) is not found.
	 * Implementations should use OutputPage::showErrorPage().
	 */
	abstract protected function showErrorPage(): void;

	/**
	 * Get and preload VE modules depending on the skin and loaded extensions.
	 *
	 * @return string[]
	 */
	protected function preloadVeModules(): array {
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
	public function onSubmit( array $data, ?HTMLForm $form = null ) {
		// Grab data directly from POST request. We should use the given $data once ::getFormFields() is implemented.
		$data = $form->getRequest()->getPostValues();
		$data['title'] = $data['entitytitle'];

		// API wants pipe-separated arrays, not CSV.
		$data = $this->store->normalizeArrayValues( $data, AbstractWishlistStore::ARRAY_DELIMITER_API );

		$path = $this->getApiPath();
		$action = $path . 'edit';

		// Set the static CommunityRequestHooks::$allowManualEditing to tell the
		// GetUserPermissionsErrors hook handler that this edit was made using the special page.
		CommunityRequestsHooks::$allowManualEditing = true;

		$context = new DerivativeContext( $this->getContext() );
		$context->setRequest( new DerivativeRequest( $this->getRequest(), [
			'action' => $action,
			$path => $this->entityId,
			'token' => $data['wpEditToken'],
			...$data,
		] ) );
		$api = new ApiMain( $context, true );
		try {
			$this->logger->debug(
				__METHOD__ . ': Executing API {0} for entity ID {1} with data {2}',
				[ $action, $this->entityId, $data ]
			);
			$api->execute();
		} catch ( ApiUsageException $e ) {
			$this->getOutput()->addJsConfigVars( [
				'intakeData' => $this->store->normalizeArrayValues( $data ),
			] );
			return $e->getStatusValue();
		}

		$this->pageTitle = Title::newFromText( $api->getResult()->getResultData()[$action][$path] );

		// Set session variables to show post-edit messages.
		$this->getRequest()->getSession()->set(
			CommunityRequestsHooks::SESSION_KEY,
			$this->entityId === null ? self::SESSION_VALUE_CREATED : self::SESSION_VALUE_UPDATED
		);
		// Redirect to entity page.
		$this->getOutput()->redirect( $this->pageTitle->getFullURL() );

		return Status::newGood( $api->getResult() );
	}

	/**
	 * @return string Either 'wish' or 'focusarea'
	 */
	abstract protected function getApiPath(): string;

	/** @inheritDoc */
	public function isListed(): bool {
		return parent::isListed() && $this->config->isEnabled();
	}

	/** @inheritDoc */
	protected function getDisplayFormat() {
		return 'codex';
	}
}
