<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageMarker;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageSettings;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;
use MessageLocalizer;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\Rdbms\IDBAccessObject;

abstract class CommunityRequestsIntegrationTestCase extends ApiTestCase {

	public const EDIT_MARK_FOR_TRANSLATION = true;
	public const EDIT_AS_TRANSLATION_SUBPAGE = false;

	protected WishlistConfig $config;
	protected AbstractWishlistStore $store;
	protected bool $translateInstalled;
	protected bool $pageLanguageUseDB;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->getServiceContainer()->get( 'CommunityRequests.WishlistConfig' );
		$this->translateInstalled = $this->getServiceContainer()
			->getExtensionRegistry()
			->isLoaded( 'Translate' );
		$this->pageLanguageUseDB = true;
		$this->overrideConfigValues( [
			MainConfigNames::NamespacesWithSubpages => [ NS_MAIN => true ],
			MainConfigNames::LanguageCode => 'en',
			MainConfigNames::PageLanguageUseDB => $this->pageLanguageUseDB,
		] );
		$this->setService( 'LocalServerObjectCache', new EmptyBagOStuff() );
	}

	abstract protected function getStore(): AbstractWishlistStore;

	protected function tearDown(): void {
		$this->resetServices();
		parent::tearDown();
	}

	/**
	 * Inserts a test wish into the wiki.
	 *
	 * @param string $page
	 * @param string $langCode
	 * @param string $created
	 * @param bool $shouldMarkForTranslation
	 * @return ?AbstractWishlistEntity
	 */
	protected function insertTestWish(
		string $page,
		string $langCode,
		string $created = '2025-01-01T00:00:00Z',
		bool $shouldMarkForTranslation = self::EDIT_MARK_FOR_TRANSLATION
	): ?AbstractWishlistEntity {
		$wishTitle = Title::newFromText( $page );
		$insertTitle = $wishTitle;
		$baseLang = $langCode;
		if ( $this->translateInstalled && $shouldMarkForTranslation === self::EDIT_AS_TRANSLATION_SUBPAGE ) {
			$insertTitle = Title::newFromText( "$page/$langCode" );
			$baseLang = $wishTitle->getPageLanguage()->getCode();
		}
		$shouldMarkForTranslation = $shouldMarkForTranslation && $this->translateInstalled;
		$description = $shouldMarkForTranslation
			// We just need one <translate> tag to mark the page for translation.
			? '<translate>Test</translate>'
			: 'Test';
		$wikitext = <<<END
{{#CommunityRequests: wish
|title = translation-$langCode-$insertTitle
|status = open
|type = change
|projects = wikipedia,commons
|phabTasks = T123,T456
|created = $created
|proposer = {$this->getTestUser()->getUser()->getName()}
|baselang = $baseLang
|description = $description}}
END;
		$ret = $this->insertPage( $insertTitle, $wikitext );
		$this->runDeferredUpdates();

		$this->assertGreaterThan( 0, $ret[ 'id' ] );
		/** @var Title $newTitle */
		$newTitle = $ret[ 'title' ];

		if ( $shouldMarkForTranslation ) {
			/** @var TranslatablePageMarker $transPageMarker */
			$transPageMarker = $this->getServiceContainer()->get( 'Translate:TranslatablePageMarker' );

			$operation = $transPageMarker->getMarkOperation(
				$newTitle->toPageRecord( IDBAccessObject::READ_LATEST ), null, false
			);
			$transPageMarker->markForTranslation(
				$operation,
				new TranslatablePageSettings( [], false, '', [], false, false, true ),
				$this->getMockBuilder( MessageLocalizer::class )->getMock(),
				$this->getTestUser()->getUser()
			);
		}

		if ( $shouldMarkForTranslation ) {
			$this->getServiceContainer()->getMainWANObjectCache()->clearProcessCache();
		}

		return $this->getStore()->get( $wishTitle, $langCode );
	}

	/**
	 * Inserts a test focus area into the wiki.
	 * @todo Add option to mark for translation
	 *
	 * @param string $page
	 * @param string $langCode
	 * @param string $created
	 * @return ?AbstractWishlistEntity
	 */
	protected function insertTestFocusArea(
		string $page,
		string $langCode,
		string $created = '2025-07-14T20:08:17Z',
		string $status = 'open'
	): ?AbstractWishlistEntity {
		$focusAreaTitle = Title::newFromText( $page );
		$wikitext = <<<END
{{#CommunityRequests: focus-area
|status = $status
|title = $focusAreaTitle
|description =  Focus area description
|short_description = Short description
|owners = tbd
|volunteers = tbd
|created = $created
|baselang = $langCode
}}
END;

		$ret = $this->insertPage( $focusAreaTitle, $wikitext );
		$this->runDeferredUpdates();

		$this->assertGreaterThan( 0, $ret[ 'id' ] );

		return $this->getStore()->get( $focusAreaTitle, $langCode );
	}
}
