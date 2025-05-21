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
use Wikimedia\Timestamp\ConvertibleTimestamp;

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
		ConvertibleTimestamp::setFakeTime( '2025-01-23T00:00:00Z' );
		$page = $this->getExistingTestPage( 'Community Wishlist/Wishes/W123' );
		$wish = new Wish(
			$page,
			'en',
			$this->getTestUser()->getUser(),
			[
				'projects' => [ 1, 2, 3 ],
				'phabTasks' => [ 123, 456 ],
				'created' => '2025-01-01T00:00:00Z',
			]
		);
		$this->wishStore->save( $wish );
		$retrievedWish = $this->wishStore->getWish( $wish->getPage(), 'en' );
		$this->assertInstanceOf( Wish::class, $retrievedWish );
		$this->assertSame( $page->getId(), $retrievedWish->getPage()->getId() );
		$this->assertArrayEquals( [ 1, 2, 3 ], $retrievedWish->getProjects() );
		$this->assertArrayEquals( [ 123, 456 ], $retrievedWish->getPhabTasks() );
		$this->assertSame( '2025-01-01T00:00:00Z', $retrievedWish->getCreated() );
		$this->assertSame( '2025-01-23T00:00:00Z', $retrievedWish->getUpdated() );
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
	 * @covers ::save
	 */
	public function testSaveNewWishWithNoProposer(): void {
		$wish = new Wish(
			Title::newFromText( 'Community Wishlist/Wishes/W123' ),
			'en',
			null,
			[]
		);
		$this->expectException( InvalidArgumentException::class );
		$this->wishStore->save( $wish );
	}

	/**
	 * @covers ::save
	 * @covers ::getWish
	 */
	public function testSaveThenResaveWithNoProposerOrCreationDate(): void {
		$user = $this->getTestUser()->getUser();
		ConvertibleTimestamp::setFakeTime( '2025-01-23T00:00:00Z' );
		$page = $this->getExistingTestPage( 'Community Wishlist/Wishes/W123' );
		ConvertibleTimestamp::setFakeTime( '2025-01-23T12:59:00Z' );
		$wish1 = new Wish( $page, 'en', $user, [ 'created' => '2025-01-23T00:00:00Z' ] );
		$this->wishStore->save( $wish1 );
		// Sanity checks.
		$retrievedWish1 = $this->wishStore->getWish( $page, 'en' );
		$this->assertSame( $user->getId(), $retrievedWish1->getProposer()->getId() );
		$this->assertSame( '2025-01-23T00:00:00Z', $retrievedWish1->getCreated() );
		$this->assertSame( '2025-01-23T12:59:00Z', $retrievedWish1->getUpdated() );
		// Now resave without a proposer or creation date, and with a different current (fake) time.
		ConvertibleTimestamp::setFakeTime( '2025-02-01T00:00:00Z' );
		$wish2 = new Wish( $page, 'en', null, [] );
		$this->wishStore->save( $wish2 );
		$retrievedWish2 = $this->wishStore->getWish( $page, 'en' );
		// Proposer should still be set to the original user.
		$this->assertSame( $user->getId(), $retrievedWish2->getProposer()->getId() );
		// Creation datestamp should be the old fake time, when $page was created.
		$this->assertSame( '2025-01-23T00:00:00Z', $retrievedWish2->getCreated() );
		// And updated should be the new fake time.
		$this->assertSame( '2025-02-01T00:00:00Z', $retrievedWish2->getUpdated() );
	}

	/**
	 * @covers ::save
	 */
	public function testSaveWithNoCreationDate(): void {
		$wish = new Wish(
			Title::newFromText( 'Community Wishlist/Wishes/W123' ),
			'en',
			$this->getTestUser()->getUser(),
			[ 'created' => null ]
		);
		$this->expectException( InvalidArgumentException::class );
		$this->wishStore->save( $wish );
	}

	/**
	 * @covers ::getWishes
	 */
	public function testGetWishes(): void {
		$wish1 = $this->getTestWish( 'Community Wishlist/Wishes/W1', 'en', '2222-01-23T00:00:00Z' );
		$wish2 = $this->getTestWish( 'Community Wishlist/Wishes/W2', 'en', '3333-01-23T00:00:00Z' );
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
		$wish1en = $this->getTestWish( 'Community Wishlist/Wishes/W1', 'en', '2222-01-23T00:00:00Z' );
		$wish1bs = $this->getTestWish( 'Community Wishlist/Wishes/W1/bs', 'bs', '2222-12-30T00:00:00Z' );
		$wish2hr = $this->getTestWish( 'Community Wishlist/Wishes/W2', 'hr', '4444-01-23T00:00:00' );
		$wish3de = $this->getTestWish( 'Community Wishlist/Wishes/W3', 'de', '3333-01-23T00:00:00' );
		$wish3fr = $this->getTestWish( 'Community Wishlist/Wishes/W3/fr', 'fr', '3333-01-23T00:00:00' );
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

	/**
	 * @covers ::getDataFromWikitext
	 */
	public function testGetDataFromWikitext(): void {
		$wikitext = <<<END
{{Community Wishlist/Wish
|status=2
|type=1
|title=<translate>Test</translate>
|description=<translate>[[<tvar name="1">Foo</tvar>|Bar]] {{baz}}</translate>

== Section ==
Example text
|created=22220123000000
}}
END;
		$title = Title::newFromText( 'W999' );
		$this->insertPage( $title, $wikitext );
		$wish = new Wish(
			$title,
			'en',
			$this->getTestUser()->getUser()
		);

		$actual = $this->wishStore->getDataFromWikitext( $wish );
		$this->assertEquals(
			"<translate>[[<tvar name=\"1\">Foo</tvar>|Bar]] {{baz}}</translate>\n\n== Section ==\nExample text",
			$actual['description']
		);
		$this->assertArrayHasKey( 'status', $actual );
		$this->assertArrayHasKey( 'type', $actual );
		$this->assertArrayHasKey( 'title', $actual );
		$this->assertArrayHasKey( 'description', $actual );
		$this->assertArrayHasKey( 'created', $actual );
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
