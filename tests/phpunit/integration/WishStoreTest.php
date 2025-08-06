<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use InvalidArgumentException;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Title\Title;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CommunityRequests
 * @group Database
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\Wish\WishStore
 */
class WishStoreTest extends CommunityRequestsIntegrationTestCase {

	protected function getStore(): WishStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	/**
	 * @covers ::save
	 * @covers ::get
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
		$this->getStore()->save( $wish );
		$retrievedWish = $this->getStore()->get( $wish->getPage(), 'en' );
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
		$this->getStore()->save( $wish );
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
		$this->getStore()->save( $wish );
	}

	/**
	 * @covers ::save
	 * @covers ::get
	 */
	public function testSaveWithFocusArea(): void {
		$wish = new Wish(
			$this->getExistingTestPage( 'Community Wishlist/Wishes/W123' ),
			'en',
			$this->getTestUser()->getUser(),
			[
				'focusArea' => $this->getExistingTestPage( 'Community Wishlist/Focus Areas/FA123' ),
				'created' => '2025-01-01T00:00:00Z',
			]
		);
		$this->getStore()->save( $wish );
		/** @var Wish $retrievedWish */
		$retrievedWish = $this->getStore()->get( $wish->getPage(), 'en' );
		$this->assertSame(
			$wish->getFocusArea()->getId(),
			$retrievedWish->getFocusArea()->getId()
		);
	}

	/**
	 * @covers ::save
	 * @covers ::get
	 */
	public function testSaveThenResaveWithNoProposerOrCreationDate(): void {
		$user = $this->getTestUser()->getUser();
		ConvertibleTimestamp::setFakeTime( '2025-01-23T00:00:00Z' );
		$page = $this->getExistingTestPage( 'Community Wishlist/Wishes/W123' );
		ConvertibleTimestamp::setFakeTime( '2025-01-23T12:59:00Z' );
		$wish1 = new Wish( $page, 'en', $user, [ 'created' => '2025-01-23T00:00:00Z' ] );
		$this->getStore()->save( $wish1 );
		// Sanity checks.
		$retrievedWish1 = $this->getStore()->get( $page, 'en' );
		$this->assertSame( $user->getId(), $retrievedWish1->getProposer()->getId() );
		$this->assertSame( '2025-01-23T00:00:00Z', $retrievedWish1->getCreated() );
		$this->assertSame( '2025-01-23T12:59:00Z', $retrievedWish1->getUpdated() );
		// Now resave without a proposer or creation date, and with a different current (fake) time.
		ConvertibleTimestamp::setFakeTime( '2025-02-01T00:00:00Z' );
		$wish2 = new Wish( $page, 'en', null, [] );
		$this->getStore()->save( $wish2 );
		$retrievedWish2 = $this->getStore()->get( $page, 'en' );
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
		$this->getStore()->save( $wish );
	}

	/**
	 * @covers ::getAll
	 */
	public function testGetAll(): void {
		$wishes = $this->getStore()->getAll( 'en', WishStore::createdField() );
		$this->assertSame( [], $wishes );

		$wish1 = $this->insertTestWish( 'Community Wishlist/Wishes/W1', 'en', '2222-01-23T00:00:00Z' );
		$wish2 = $this->insertTestWish( 'Community Wishlist/Wishes/W2', 'en', '3333-01-23T00:00:00Z' );

		$wishes = $this->getStore()->getAll( 'en', WishStore::createdField() );
		$this->assertCount( 2, $wishes );
		$this->assertContainsOnlyInstancesOf( Wish::class, $wishes );
		$this->assertSame( $wish2->getPage()->getId(), $wishes[0]->getPage()->getId() );
		$this->assertSame( $wish1->getPage()->getId(), $wishes[1]->getPage()->getId() );
	}

	/**
	 * @covers ::getAll
	 * @covers ::delete
	 */
	public function testGetWishesLangFallbacks(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Translate' );
		$this->insertTestWish(
			'Community Wishlist/Wishes/W1',
			'en',
			'2222-01-23T00:00:00Z'
		);
		$this->insertTestWish(
			'Community Wishlist/Wishes/W1',
			'bs',
			'2222-12-30T00:00:00Z',
			CommunityRequestsIntegrationTestCase::EDIT_AS_TRANSLATION_SUBPAGE
		);
		$this->insertTestWish(
			'Community Wishlist/Wishes/W2',
			'hr',
			'4444-01-23T00:00:00Z'
		);
		$this->insertTestWish(
			'Community Wishlist/Wishes/W3',
			'de',
			'3333-01-23T00:00:00Z'
		);
		$this->insertTestWish(
			'Community Wishlist/Wishes/W3',
			'fr',
			'3333-01-23T00:00:00',
			CommunityRequestsIntegrationTestCase::EDIT_AS_TRANSLATION_SUBPAGE
		);

		// 'sh' should return W2, W3/de, W1/bs
		$wishes = $this->getStore()->getAll( 'sh', WishStore::createdField() );
		$this->assertCount( 3, $wishes );
		$this->assertSame( 'Community_Wishlist/Wishes/W2', $wishes[0]->getPage()->getDBkey() );
		$this->assertSame( 'hr', $wishes[0]->getLang() );
		$this->assertSame( 'Community_Wishlist/Wishes/W3', $wishes[1]->getPage()->getDBkey() );
		$this->assertSame( 'de', $wishes[1]->getLang() );
		$this->assertSame( 'Community_Wishlist/Wishes/W1', $wishes[2]->getPage()->getDBkey() );
		$this->assertSame( 'bs', $wishes[2]->getLang() );

		// This simulates action=delete on W1/bs.
		$this->deletePage( 'Community Wishlist/Wishes/W1/bs' );

		// With the deletion of W1/bs, 'sh' should return W2, W3/de, W1/en
		$wishes = $this->getStore()->getAll( 'sh', WishStore::createdField() );
		$this->assertCount( 3, $wishes );
		$this->assertSame( 'Community_Wishlist/Wishes/W2', $wishes[0]->getPage()->getDBkey() );
		$this->assertSame( 'Community_Wishlist/Wishes/W3', $wishes[1]->getPage()->getDBkey() );
		$this->assertSame( 'de', $wishes[1]->getLang() );
		$this->assertSame( 'Community_Wishlist/Wishes/W1', $wishes[2]->getPage()->getDBkey() );
		$this->assertSame( 'en', $wishes[2]->getLang() );
	}

	/**
	 * @covers ::getAll
	 */
	public function testGetWishesLimitEmulation(): void {
		$this->insertTestWish( 'Community Wishlist/Wishes/W1', 'hr' );
		$this->insertTestWish(
			'Community Wishlist/Wishes/W1',
			'en',
			'2222-01-23T00:00:00Z',
			CommunityRequestsIntegrationTestCase::EDIT_AS_TRANSLATION_SUBPAGE
		);
		$this->insertTestWish( 'Community Wishlist/Wishes/W2', 'hr' );

		$wishes = $this->getStore()->getAll(
			'en',
			WishStore::createdField(),
			WishStore::SORT_DESC,
			2
		);
		$this->assertCount( 2, $wishes );
		$this->assertSame( 'Community_Wishlist/Wishes/W1', $wishes[0]->getPage()->getDBkey() );
		$this->assertSame( 'en', $wishes[0]->getLang() );
		$this->assertSame( 'Community_Wishlist/Wishes/W2', $wishes[1]->getPage()->getDBkey() );
	}

	/**
	 * @covers ::getDataFromWikitext
	 */
	public function testGetDataFromWikitext(): void {
		$wikitext = <<<END
{{#CommunityRequests: wish
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

		$actual = $this->getStore()->getDataFromWikitext( $wish->getPage()->getId() );
		$this->assertSame(
			"<translate>[[<tvar name=\"1\">Foo</tvar>|Bar]] {{baz}}</translate>\n\n== Section ==\nExample text",
			$actual['description']
		);
		$this->assertArrayHasKey( 'status', $actual );
		$this->assertArrayHasKey( 'type', $actual );
		$this->assertArrayHasKey( 'title', $actual );
		$this->assertArrayHasKey( 'description', $actual );
		$this->assertArrayHasKey( 'created', $actual );
	}

	/**
	 * @covers ::getIdFromInput
	 */
	public function testGetIdFromInput(): void {
		$this->assertSame( 123, $this->getStore()->getIdFromInput( 123 ) );
		$this->assertSame( 123, $this->getStore()->getIdFromInput( '123' ) );
		$this->assertSame( 123, $this->getStore()->getIdFromInput( 'W123' ) );
		$this->assertSame( 123, $this->getStore()->getIdFromInput( 'Community Wishlist/Wishes/W123' ) );
		$this->assertSame( 123, $this->getStore()->getIdFromInput( 'Community Wishlist/Wishes/W123/fr' ) );
		$this->assertNull( $this->getStore()->getIdFromInput( 'Not a wish page' ) );
		$this->overrideConfigValue( 'CommunityRequestsWishPagePrefix', '2025 Community Wishlist/Wishes/W' );
		$this->assertSame( 503, $this->getStore()->getIdFromInput( '2025 Community Wishlist/Wishes/W503' ) );
	}

	/**
	 * @covers ::saveTranslations
	 */
	public function testTitleOverMaxBytes(): void {
		$title = $this->getExistingTestPage( 'Community Wishlist/Wishes/W123' );
		$wish = new Wish(
			$title,
			'en',
			$this->getTestUser()->getUser(),
			[
				'title' => str_repeat( 'a', 500 ),
				'created' => '2025-01-01T00:00:00Z',
			]
		);
		$this->getStore()->save( $wish );
		$wish = $this->getStore()->get( $title );
		$this->assertSame( str_repeat( 'a', 255 ), $wish->getTitle() );
	}
}
