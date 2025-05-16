<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use InvalidArgumentException;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageMarker;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageSettings;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use MessageLocalizer;
use MockTitleTrait;
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
		$this->overrideConfigValues( [ MainConfigNames::NamespacesWithSubpages => [ NS_MAIN => true ] ] );
	}

	/**
	 * @covers ::save
	 * @covers ::getWish
	 */
	public function testSaveAndGetWish(): void {
		$wish = $this->getTestWish( 'W123' );
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
		$fauxPage = Title::newFromText( 'W111' );
		$wish = new Wish(
			$fauxPage,
			'en',
			$this->getTestUser()->getUser(),
			[
				'type' => 1,
				'status' => 2,
				'focusAreaId' => null,
				'voteCount' => 0,
				'created' => '22220123000000',
				'title' => 'translation-en-W111',
				'projects' => [ 0, 1, 2 ],
				'otherProject' => 'Test Other Project',
				'phabTasks' => [ 123, 456 ],
			]
		);
		$this->expectException( InvalidArgumentException::class );
		$this->wishStore->save( $wish );
	}

	/**
	 * @covers ::getWishes
	 */
	public function testGetWishes(): void {
		$wish1 = $this->getTestWish( 'W1', 'en', '22220123000000' );
		$wish2 = $this->getTestWish( 'W2', 'en', '33330123000000' );
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
	 */
	public function testGetWishesLangFallbacks(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Translate' );
		$wish1en = $this->getTestWish( 'W1', 'en', '22220123000000' );
		$wish1bs = $this->getTestWish( 'W1/bs', 'bs', '2222123000000' );
		$wish2hr = $this->getTestWish( 'W2', 'hr', '44440123000000' );
		$wish3de = $this->getTestWish( 'W3', 'de', '33330123000000' );
		$this->wishStore->save( $wish1en );
		$this->wishStore->save( $wish1bs );
		$this->wishStore->save( $wish2hr );
		$this->wishStore->save( $wish3de );

		$wishes = $this->wishStore->getWishes( 'sh' );
		$this->assertCount( 3, $wishes );
		$this->assertEquals( 'W2', $wishes[0]->getPage()->getDBkey() );
		$this->assertEquals( 'translation-hr-W2', $wishes[0]->getTitle() );
		$this->assertEquals( 'W3', $wishes[1]->getPage()->getDBkey() );
		$this->assertEquals( 'translation-de-W3', $wishes[1]->getTitle() );
		$this->assertEquals( 'W1', $wishes[2]->getPage()->getDBkey() );
		$this->assertEquals( 'translation-bs-W1/bs', $wishes[2]->getTitle() );
	}

	private function getTestWish(
		?string $title = 'W123',
		string $langCode = 'en',
		string $created = '22220123000000'
	): Wish {
		$title = Title::newFromText( $title );
		$shouldMarkForTranslation = $this->translateInstalled && !str_contains( $title->getDBkey(), '/' );
		$this->insertPage( $title, $shouldMarkForTranslation ? '<translate>test</translate>' : 'Test' );
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
		return new Wish(
			$title,
			$langCode,
			$this->getTestUser()->getUser(),
			[
				'type' => 1,
				'status' => 2,
				'focusAreaId' => null,
				'voteCount' => 0,
				'created' => $created,
				'title' => "translation-$langCode-$title",
				'projects' => [ 0, 1, 2 ],
				'otherProject' => 'Test Other Project',
				'phabTasks' => [ 123, 456 ],
			]
		);
	}
}
