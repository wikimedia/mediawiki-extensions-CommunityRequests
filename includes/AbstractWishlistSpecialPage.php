<?php

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use RuntimeException;

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
	 * Load an existing entity (wish or focus area) from the store.
	 *
	 * @param int $entityId
	 * @return bool
	 */
	protected function loadExistingEntity( int $entityId ): bool {
		$this->pageTitle = Title::newFromText( $this->store->getPagePrefix() . $entityId );
		$entity = $this->store->get( $this->pageTitle );

		if ( !$entity ) {
			$this->showErrorPage();
			return false;
		}

		$wikitextData = $this->store->getDataFromWikitext( $entity->getPage()->getId() );
		if ( $wikitextData === null ) {
			throw new RuntimeException( 'Failed to load wikitext data for wishlist entity' );
		}
		$wikitextFields = $this->store->getWikitextFields();
		$templateParams = $this->store->getTemplateParams();
		$this->getOutput()->addJsConfigVars( [
			'intakeId' => $entityId,
			'intakeData' => [
				...$entity->toArray( $this->config ),
				...array_map(
					static fn ( $field ) => $wikitextData[ $templateParams[ $field ] ],
					array_combine( $wikitextFields, $wikitextFields ),
				),
				'baseRevId' => $wikitextData[ 'baseRevId' ],
			],
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
	public function isListed(): bool {
		return parent::isListed() && $this->config->isEnabled();
	}

	/** @inheritDoc */
	protected function getDisplayFormat() {
		return 'codex';
	}
}
