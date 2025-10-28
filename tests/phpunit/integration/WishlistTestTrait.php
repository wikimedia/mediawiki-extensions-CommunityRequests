<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageMarker;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageSettings;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MessageLocalizer;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\TestCase;
use TestUser;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Trait for testing Translate extension functionality.
 *
 * Classes using this trait must extend MediaWikiIntegrationTestCase,
 * and must NOT implement their own setUp() and tearDown() methods.
 */
trait WishlistTestTrait {

	protected WishlistConfig $config;
	protected AbstractWishlistStore $store;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->getServiceContainer()->get( 'CommunityRequests.WishlistConfig' );
		$this->overrideConfigValues( [
			MainConfigNames::NamespacesWithSubpages => [ NS_MAIN => true ],
			MainConfigNames::LanguageCode => 'en',
			MainConfigNames::PageLanguageUseDB => true,
		] );
		$this->setService( 'LocalServerObjectCache', new EmptyBagOStuff() );
	}

	/**
	 * Inserts a test wish into the wiki.
	 *
	 * Any fields containing <translate> will cause the page
	 * to be marked for translation, and for jobs to be ran.
	 *
	 * @param Title|string|null $wishPage Root page for the wish.
	 *   null to use an auto-generated title.
	 * @param string $lang Either the base language (for new wishes),
	 *   or the language of the translation (for translated wishes).
	 * @param array $data Be sure to specify PARAM_BASE_LANG if $lang is different.
	 * @param bool $markForTranslation Whether to mark the page for translation if it contains <translate> tags.
	 *   This exists for a few narrow test cases where we want to test the unmarked state.
	 * @return ?Wish
	 */
	protected function insertTestWish(
		// phpcs:ignore MediaWiki.Usage.NullableType.ExplicitNullableTypes -- false positive
		Title|string|null $wishPage = null,
		string $lang = 'en',
		array $data = [],
		bool $markForTranslation = true,
	): ?Wish {
		$defaultData = [
			Wish::PARAM_TITLE => 'Test Wish',
			Wish::PARAM_DESCRIPTION => 'This is a test wish.',
			Wish::PARAM_AUDIENCE => 'everyone',
			Wish::PARAM_STATUS => 'under-review',
			Wish::PARAM_TYPE => 'change',
			Wish::PARAM_TAGS => 'multimedia,newcomers',
			Wish::PARAM_PHAB_TASKS => 'T123,T456',
			Wish::PARAM_CREATED => '2025-01-01T00:00:00Z',
			Wish::PARAM_PROPOSER => $this->getTestUser()->getUser()->getName(),
			Wish::PARAM_BASE_LANG => $lang,
		];
		/** @var Wish */
		return $this->insertTestEntity( $wishPage, $lang, $data, $defaultData, $markForTranslation );
	}

	/**
	 * Inserts a test focus area into the wiki.
	 *
	 * Any fields containing <translate> will cause the page
	 * to be marked for translation, and for jobs to be ran.
	 *
	 * @param Title|string|null $focusAreaPage Root page for the focus area.
	 *   null to use an auto-generated title.
	 * @param string $lang Either the base language (for new wishes),
	 *   or the language of the translation (for translated wishes).
	 * @param array $data Be sure to specify PARAM_BASE_LANG if $lang is different.
	 * @param bool $markForTranslation Whether to mark the page for translation if it contains <translate> tags.
	 *  This exists for a few narrow test cases where we want to test the unmarked state.
	 * @return ?FocusArea
	 */
	protected function insertTestFocusArea(
		// phpcs:ignore MediaWiki.Usage.NullableType.ExplicitNullableTypes -- false positive
		Title|string|null $focusAreaPage = null,
		string $lang = 'en',
		array $data = [],
		bool $markForTranslation = true,
	): ?FocusArea {
		$defaultData = [
			FocusArea::PARAM_TITLE => 'Test Focus Area',
			FocusArea::PARAM_DESCRIPTION => 'This is a test focus area.',
			FocusArea::PARAM_SHORT_DESCRIPTION => 'Short description',
			FocusArea::PARAM_STATUS => 'in-progress',
			FocusArea::PARAM_OWNERS => 'tbd',
			FocusArea::PARAM_VOLUNTEERS => 'tbd',
			FocusArea::PARAM_CREATED => '2025-01-01T00:00:00Z',
			FocusArea::PARAM_BASE_LANG => $lang,
		];
		/** @var FocusArea */
		return $this->insertTestEntity( $focusAreaPage, $lang, $data, $defaultData, $markForTranslation );
	}

	private function insertTestEntity(
		// phpcs:ignore MediaWiki.Usage.NullableType.ExplicitNullableTypes -- false positive
		Title|string|null $title = null,
		string $lang = 'en',
		array $data = [],
		array $defaultData = [],
		bool $markForTranslation = true,
	): ?AbstractWishlistEntity {
		if ( $title === null ) {
			$id = $this->getStore()->getNewId();
			$title = Title::newFromText( $this->getStore()->getPagePrefix() . $id );
		} elseif ( is_string( $title ) ) {
			$title = Title::newFromText( $title );
		}
		if ( $this->config->isFocusAreaPage( $title ) ) {
			$entityType = 'focus-area';
			$class = FocusArea::class;
		} else {
			$entityType = 'wish';
			$class = Wish::class;
		}

		$shouldMarkForTranslation = $markForTranslation &&
			str_contains( implode( '', array_values( $data ) ), '<translate' );
		$data = array_merge( $defaultData, $data );

		$insertTitle = $title;
		if ( $lang !== $data[$class::PARAM_BASE_LANG] ) {
			$insertTitle = $title->getSubpage( $lang );
		}

		// Build the wikitext.
		$args = array_map(
			static fn ( $param ) => "$param = {$data[$param]}",
			array_keys( $defaultData )
		);
		$wikitext = "{{#CommunityRequests: $entityType\n" .
			'|' . implode( "\n|", $args ) .
			"\n}}";

		// Insert and assert existence and language.
		$ret = $this->insertPage( $insertTitle, $wikitext );
		$this->assertGreaterThan( 0, $ret['id'] );
		$this->runDeferredUpdates();
		$newTitle = Title::newFromText( $ret['title']->getFullText() );
		$this->assertSame( $lang, $newTitle->getPageLanguage()->getCode() );

		$fetchMethod = AbstractWishlistStore::FETCH_WIKITEXT_RAW;
		$translateInstalled = $this->getServiceContainer()
			->getExtensionRegistry()
			->isLoaded( 'Translate' );
		if ( $shouldMarkForTranslation && $translateInstalled ) {
			$this->markForTranslation( $ret['title'] );
			$fetchMethod = AbstractWishlistStore::FETCH_WIKITEXT_TRANSLATED;
		}

		return $this->getStore()->get( $title, $lang, $fetchMethod );
	}

	/**
	 * Marks a page for translation.
	 *
	 * This method should be called after inserting a test wish that contains translatable content.
	 */
	public function markForTranslation( Title|string $title ): void {
		if ( is_string( $title ) ) {
			$title = Title::newFromText( $title );
		}
		/** @var TranslatablePageMarker $transPageMarker */
		$transPageMarker = $this->getServiceContainer()->get( 'Translate:TranslatablePageMarker' );

		$operation = $transPageMarker->getMarkOperation(
			$title->toPageRecord( IDBAccessObject::READ_LATEST ), null, false
		);
		$transPageMarker->markForTranslation(
			$operation,
			new TranslatablePageSettings( [], false, '', [], false, false, true ),
			$this->getMockBuilder( MessageLocalizer::class )->getMock(),
			$this->getTestUser()->getUser()
		);

		$this->getServiceContainer()->getMainWANObjectCache()->clearProcessCache();
		$this->runJobs( [ 'numJobs' => 1 ], [ 'type' => 'UpdateTranslatablePageJob' ] );
		$this->runJobs( [ 'numJobs' => 1 ], [ 'type' => 'RenderTranslationPageJob' ] );
	}

	abstract protected function getStore(): AbstractWishlistStore;

	/** @see TestCase::getMockBuilder */
	abstract public function getMockBuilder( string $className ): MockBuilder;

	/**
	 * @param string|string[] $groups
	 * @return TestUser
	 * @see \MediaWikiIntegrationTestCase::getTestUser
	 */
	abstract protected function getTestUser( $groups = [] );

	/**
	 * @return MediaWikiServices
	 * @see \MediaWikiIntegrationTestCase::getServiceContainer
	 */
	abstract public function getServiceContainer();

	/** @see \MediaWikiIntegrationTestCase::runJobs */
	abstract public function runJobs( array $assertOptions = [], array $runOptions = [] );

	/**
	 * @param string|LinkTarget|PageIdentity $title
	 * @param string $text
	 * @param int|null $namespace
	 * @param User|null $user
	 * @return array
	 * @see \MediaWikiIntegrationTestCase::insertPage
	 */
	abstract protected function insertPage(
		$title,
		$text = 'Sample page for unit test.',
		$namespace = null,
		?User $user = null
	);

	protected function tearDown(): void {
		$this->resetServices();
		parent::tearDown();
	}
}
