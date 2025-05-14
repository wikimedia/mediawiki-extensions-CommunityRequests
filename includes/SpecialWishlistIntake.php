<?php

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

/**
 * JS-only Special page for submitting a new community request.
 */
class SpecialWishlistIntake extends SpecialPage {

	protected ParserFactory $parserFactory;
	protected ?Config $config;

	/** @inheritDoc */
	public function __construct( ParserFactory $parserFactory, ?Config $config = null ) {
		parent::__construct( 'WishlistIntake' );
		$this->parserFactory = $parserFactory;
		$this->config = $config;
	}

	/** @inheritDoc */
	public function getDescription(): Message {
		return $this->msg( 'communityrequests-wishlistintake' );
	}

	/** @inheritDoc */
	public function isListed(): bool {
		return parent::isListed() && $this->config->get( 'CommunityRequestsEnable' );
	}

	/** @inheritDoc */
	public function execute( $wishId ): void {
		$this->requireNamedUser( 'communityrequests-please-log-in' );

		if ( !$this->getConfig()->get( 'CommunityRequestsEnable' ) ) {
			$this->setHeaders();
			$this->getOutput()->addWikiMsg( 'communityrequests-disabled' );
			return;
		}

		$wishId ??= $this->getRequest()->getInt( 'id' );
		if ( !$wishId ) {
			$pageTitle = $this->config->get( 'CommunityRequestsWishPagePrefix' ) . $wishId;
			// TODO: Fetch from db and pass Wish object instead of parsing wikitext in JS.
			// Pass the wish title to the JS.
			$this->getOutput()->addJsConfigVars( 'intakeWishTitle', $pageTitle );
			$this->getSkin()->setRelevantTitle( Title::newFromText( $pageTitle ) );
		}

		$this->getOutput()->addJsConfigVars( [
			'parserTags' => $this->parserFactory->getInstance()->getTags()
		] );
		$this->getOutput()->addElement( 'div', [ 'class' => 'wishlist-intake-container' ] );
		$this->getOutput()->addModules( 'ext.communityrequests.intake' );

		// For VisualEditor.
		// TODO: Remove hard dependency on VE
		$this->getOutput()->addJsConfigVars( 'intakeVeModules', $this->preloadVeModules() );

		parent::execute( $wishId );
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
}
