<?php

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Config\Config;
use MediaWiki\Message\Message;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use ParserFactory;

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
	public function execute( $wishSlug ): ?string {
		$this->requireNamedUser( 'communityrequests-please-log-in' );

		if ( !$this->getConfig()->get( 'CommunityRequestsEnable' ) ) {
			$this->setHeaders();
			$this->getOutput()->addWikiMsg( 'communityrequests-disabled' );
			return null;
		}

		$wishSlug ??= $this->getRequest()->getText( 'target' );
		if ( $wishSlug ) {
			$pageTitle = $this->config->get( 'CommunityRequestsWishPagePrefix' ) . $wishSlug;
			// TODO: Fetch from db and pass Wish object instead of parsing wikitext in JS.
			// Pass the wish title to the JS.
			$this->getOutput()->addJsConfigVars( 'intakeWishTitle', $pageTitle );
			$this->getSkin()->setRelevantTitle( Title::newFromText( $pageTitle ) );
		}

		$this->getOutput()->addJsConfigVars( [
			'parserTags' => $this->parserFactory->getInstance()->getTags(),
			'wgCommunityRequestsHomepage' => $this->config->get( 'CommunityRequestsHomepage' ),
		] );
		$this->getOutput()->addElement( 'div', [ 'class' => 'wishlist-intake-container' ] );
		$this->getOutput()->addModules( 'ext.communityrequests.intake' );

		// For VisualEditor.
		// TODO: Remove hard dependency on VE
		$this->getOutput()->addJsConfigVars( 'intakeVeModules', $this->preloadVeModules() );

		return parent::execute( $wishSlug );
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
