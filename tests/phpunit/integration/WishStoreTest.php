<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use InvalidArgumentException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageMarker;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageSettings;
use MediaWiki\MainConfigNames;
use MediaWiki\Specials\SpecialPageLanguage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use MessageLocalizer;
use MockTitleTrait;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @group CommunityRequests
 * @group Database
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\Wish\WishStore
 */
class WishStoreTest extends MediaWikiIntegrationTestCase {

	use MockTitleTrait;

	private WishStore $wishStore;
	private bool $translateInstalled;

	protected function setUp(): void {
		parent::setUp();
		$this->wishStore = $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
		$this->translateInstalled = $this->getServiceContainer()
			->getExtensionRegistry()
			->isLoaded( 'Translate' );
		$this->overrideConfigValues( [
			MainConfigNames::NamespacesWithSubpages => [ NS_MAIN => true ],
			'EnablePageTranslation' => true,
		] );
		$this->setService( 'LocalServerObjectCache', new EmptyBagOStuff() );
	}

	protected function tearDown(): void {
		$this->resetServices();
		parent::tearDown();
	}

	/**
	 * @covers ::save
	 * @covers ::getWish
	 */
	public function testSaveAndGetWish(): void {
		$wish = $this->getTestWish( 'Community Wishlist/Wishes/W123' );
		$this->wishStore->save( $wish );
		$retrievedWish = $this->wishStore->getWish( $wish->getPage(), 'en' );
		$this->assertInstanceOf( Wish::class, $retrievedWish );
		$this->assertSame( $wish->getPage()->getId(), $retrievedWish->getPage()->getId() );
		$this->assertSame( $wish->getProjects(), $retrievedWish->getProjects() );
		$this->assertSame( $wish->getPhabTasks(), $retrievedWish->getPhabTasks() );
	}

	/**
	 * @covers ::save
	 */
	public function testSaveWishWithNoPage(): void {
		$fauxPage = Title::newFromText( 'Community Wishlist/Wishes/W111' );
		$wish = new Wish(
			$fauxPage,
			'en',
			$this->getTestUser()->getUser(),
			[]
		);
		$this->expectException( InvalidArgumentException::class );
		$this->wishStore->save( $wish );
	}

	/**
	 * @covers ::getWishes
	 */
	public function testGetWishes(): void {
		$wish1 = $this->getTestWish( 'Community Wishlist/Wishes/W1', 'en', '22220123000000' );
		$wish2 = $this->getTestWish( 'Community Wishlist/Wishes/W2', 'en', '33330123000000' );
		$this->wishStore->save( $wish1 );
		$this->wishStore->save( $wish2 );

		$wishes = $this->wishStore->getWishes( 'en' );
		$this->assertCount( 2, $wishes );
		$this->assertContainsOnlyInstancesOf( Wish::class, $wishes );
		$this->assertSame( $wish2->getPage()->getId(), $wishes[0]->getPage()->getId() );
		$this->assertSame( $wish1->getPage()->getId(), $wishes[1]->getPage()->getId() );
	}

	/**
	 * @covers ::getWishes
	 * @covers ::delete
	 */
	public function testGetWishesLangFallbacks(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Translate' );
		$wish1en = $this->getTestWish( 'Community Wishlist/Wishes/W1', 'en', '22220123000000' );
		$wish1bs = $this->getTestWish( 'Community Wishlist/Wishes/W1/bs', 'bs', '2222123000000' );
		$wish2hr = $this->getTestWish( 'Community Wishlist/Wishes/W2', 'hr', '44440123000000' );
		$wish3de = $this->getTestWish( 'Community Wishlist/Wishes/W3', 'de', '33330123000000' );
		$wish3fr = $this->getTestWish( 'Community Wishlist/Wishes/W3/fr', 'fr', '33330123000000' );
		$this->wishStore->save( $wish1en );
		$this->wishStore->save( $wish1bs );
		$this->wishStore->save( $wish2hr );
		$this->wishStore->save( $wish3de );
		$this->wishStore->save( $wish3fr );

		// 'sh' should return W2, W3/de, W1/bs
		$wishes = $this->wishStore->getWishes( 'sh' );
		$this->assertCount( 3, $wishes );
		$this->assertEquals( 'Community_Wishlist/Wishes/W2', $wishes[0]->getPage()->getDBkey() );
		$this->assertEquals( 'translation-hr-Community Wishlist/Wishes/W2', $wishes[0]->getTitle() );
		$this->assertEquals( 'Community_Wishlist/Wishes/W3', $wishes[1]->getPage()->getDBkey() );
		$this->assertEquals( 'translation-de-Community Wishlist/Wishes/W3', $wishes[1]->getTitle() );
		$this->assertEquals( 'Community_Wishlist/Wishes/W1', $wishes[2]->getPage()->getDBkey() );
		$this->assertEquals( 'translation-bs-Community Wishlist/Wishes/W1/bs', $wishes[2]->getTitle() );

		$this->wishStore->delete( $wish1bs );

		// With the deletion of W1/bs, 'sh' should return W2, W3/de, W1/en
		$wishes = $this->wishStore->getWishes( 'sh' );
		$this->assertCount( 3, $wishes );
		$this->assertEquals( 'Community_Wishlist/Wishes/W1', $wishes[2]->getPage()->getDBkey() );
		$this->assertEquals( 'translation-en-Community Wishlist/Wishes/W1', $wishes[2]->getTitle() );
	}

	private function getTestWish(
		?string $title = 'Community Wishlist/Wishes/W123',
		string $langCode = 'en',
		string $created = '22220123000000'
	): Wish {
		$title = Title::newFromText( $title );
		$shouldChangeLanguage = $this->translateInstalled && $langCode !== 'en';
		$shouldMarkForTranslation = $this->translateInstalled && !str_ends_with( $title->getDBkey(), "/$langCode" );

		/** @var Title $title */
		$title = $this->insertPage(
			$title,
			$shouldMarkForTranslation ? '<translate>test</translate>' : 'Test'
		)[ 'title' ];

		if ( $shouldChangeLanguage ) {
			// TODO: WishStore should change the language upon creation of the wish via a hook
			$context = RequestContext::getMain();
			$context->setUser( $this->getTestUser()->getUser() );
			SpecialPageLanguage::changePageLanguage( $context, $title, $langCode );
		}

		if ( $shouldMarkForTranslation ) {
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
		}

		if ( $shouldChangeLanguage || $shouldMarkForTranslation ) {
			$this->getServiceContainer()->getMainWANObjectCache()->clearProcessCache();
		}

		return new Wish(
			$title,
			$langCode,
			$this->getTestUser()->getUser(),
			[
				'created' => $created,
				'title' => "translation-$langCode-$title",
				'projects' => [ 1, 2, 3 ],
				'phabTasks' => [ 123, 456 ],
			]
		);
	}

	/**
	 * @covers ::isWishPage
	 */
	public function testIsWishPage(): void {
		$this->assertFalse( $this->wishStore->isWishPage( Title::newFromText( 'W123' ) ) );
		$this->assertTrue( $this->wishStore->isWishPage( 'Community Wishlist/Wishes/W123' ) );
		$this->assertTrue( $this->wishStore->isWishPage( Title::newFromText( 'Community Wishlist/Wishes/W123/fr' ) ) );
	}
}
