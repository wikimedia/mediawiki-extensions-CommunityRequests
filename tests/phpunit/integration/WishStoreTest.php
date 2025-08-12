<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use InvalidArgumentException;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\Wish\WishStore
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractWishlistStore
 * @covers \MediaWiki\Extension\CommunityRequests\EntityFactory
 */
class WishStoreTest extends MediaWikiIntegrationTestCase {
	use WishlistTestTrait;

	protected function getStore(): WishStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	public function testSaveAndGetWish(): void {
		ConvertibleTimestamp::setFakeTime( '2025-01-23T00:00:00Z' );
		$page = $this->getExistingTestPage( 'Community Wishlist/Wishes/W123' );
		$wish = new Wish(
			$page,
			'en',
			$this->getTestUser()->getUser(),
			[
				Wish::PARAM_PROJECTS => [ 1, 2, 3 ],
				Wish::PARAM_PHAB_TASKS => [ 123, 456 ],
				Wish::PARAM_CREATED => '2025-01-01T00:00:00Z',
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

	public function testSaveWithFocusArea(): void {
		$wish = new Wish(
			$this->getExistingTestPage( 'Community Wishlist/Wishes/W123' ),
			'en',
			$this->getTestUser()->getUser(),
			[
				Wish::PARAM_FOCUS_AREA => $this->getExistingTestPage( 'Community Wishlist/Focus Areas/FA123' ),
				Wish::PARAM_CREATED => '2025-01-01T00:00:00Z',
			]
		);
		$this->getStore()->save( $wish );
		/** @var Wish $retrievedWish */
		$retrievedWish = $this->getStore()->get( $wish->getPage(), 'en' );
		$this->assertSame(
			$wish->getFocusAreaPage()->getId(),
			$retrievedWish->getFocusAreaPage()->getId()
		);
	}

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

	public function testSaveWithNoCreationDate(): void {
		$wish = new Wish(
			Title::newFromText( 'Community Wishlist/Wishes/W123' ),
			'en',
			$this->getTestUser()->getUser(),
			[ Wish::PARAM_CREATED => null ]
		);
		$this->expectException( InvalidArgumentException::class );
		$this->getStore()->save( $wish );
	}

	public function testGetAll(): void {
		$wishes = $this->getStore()->getAll( 'en', WishStore::createdField() );
		$this->assertSame( [], $wishes );

		$wish1 = $this->insertTestWish(
			'Community Wishlist/Wishes/W1',
			'en',
			[ Wish::PARAM_CREATED => '2222-01-23T00:00:00Z' ],
		);
		$wish2 = $this->insertTestWish(
			'Community Wishlist/Wishes/W2',
			'en',
			[ Wish::PARAM_CREATED => '3333-01-23T00:00:00Z' ],
		);

		$wishes = $this->getStore()->getAll( 'en', WishStore::createdField() );
		$this->assertCount( 2, $wishes );
		$this->assertContainsOnlyInstancesOf( Wish::class, $wishes );
		$this->assertSame( $wish2->getPage()->getId(), $wishes[0]->getPage()->getId() );
		$this->assertSame( $wish1->getPage()->getId(), $wishes[1]->getPage()->getId() );
	}

	public function testGetWishesLangFallbacks(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Translate' );

		$this->insertTestWish(
			'Community Wishlist/Wishes/W1',
			'en',
			[
				Wish::PARAM_TITLE => '<translate>Example wish</translate>',
				Wish::PARAM_CREATED => '2222-01-23T00:00:00Z'
			],
		);
		$this->insertTestWish(
			'Community Wishlist/Wishes/W1',
			'bs',
			[
				Wish::PARAM_CREATED => '2222-12-30T00:00:00Z',
				Wish::PARAM_BASE_LANG => 'en',
			],
		);
		$this->insertTestWish(
			'Community Wishlist/Wishes/W2',
			'hr',
			[
				Wish::PARAM_CREATED => '4444-01-23T00:00:00Z',
			],
		);
		$this->insertTestWish(
			'Community Wishlist/Wishes/W3',
			'de',
			[
				Wish::PARAM_TITLE => '<translate>Beispielwunsch</translate>',
				Wish::PARAM_CREATED => '3333-01-23T00:00:00Z',
			],
		);
		$this->insertTestWish(
			'Community Wishlist/Wishes/W3',
			'fr',
			[
				Wish::PARAM_CREATED => '3333-01-23T00:00:00',
				Wish::PARAM_BASE_LANG => 'de',
			],
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

	public function testGetWishesLimitEmulation(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Translate' );

		$this->insertTestWish(
			'Community Wishlist/Wishes/W1',
			'hr',
			[
				Wish::PARAM_TITLE => '<translate>W1 (hr)</translate>',
				// Ensure with SORT_DESC by createdField that this wish is returned last.
				Wish::PARAM_CREATED => '3333-01-23T00:00:00Z',
			],
		);
		$this->insertTestWish(
			'Community Wishlist/Wishes/W1',
			'en',
			[
				Wish::PARAM_TITLE => 'W1 (hr)/en',
				// Translations have the same created date as the original wish.
				Wish::PARAM_CREATED => '3333-01-23T00:00:00Z',
				Wish::PARAM_BASE_LANG => 'hr',
			],
		);
		$this->insertTestWish(
			'Community Wishlist/Wishes/W2',
			'hr',
			[
				Wish::PARAM_TITLE => '<translate>W2 (hr)</translate>',
				Wish::PARAM_CREATED => '2222-01-23T00:00:00Z',
			],
		);

		$wishes = $this->getStore()->getAll(
			'en',
			WishStore::createdField(),
			WishStore::SORT_DESC,
			2
		);
		$this->assertCount( 2, $wishes );
		$this->assertSame( 'W1 (hr)/en', $wishes[0]->getTitle() );
		$this->assertSame( 'W2 (hr)', $wishes[1]->getTitle() );
	}

	public function testGetDataFromWikitextContent(): void {
		$wikitext = <<<END
{{#CommunityRequests: wish
|title=Test
|status=  declined
|type=

feature

|description=<translate>[[<tvar name="1">Foo</tvar>|Bar]] {{baz}}</translate>

== Section ==<!-- comments! -->
Example text
|created=2222-01-23T00:00:00Z
}}
END;
		$title = Title::newFromText( 'W999' );
		$this->insertPage( $title, $wikitext );

		$actual = $this->getStore()->getDataFromPageId( $title->getId() );
		$this->assertSame( 'declined', $actual[Wish::PARAM_STATUS] );
		$this->assertSame( 'feature', $actual[Wish::PARAM_TYPE] );
		$this->assertSame( 'Test', $actual[Wish::PARAM_TITLE] );
		$this->assertSame(
			"<translate>[[<tvar name=\"1\">Foo</tvar>|Bar]] {{baz}}</translate>" .
				"\n\n== Section ==<!-- comments! -->\nExample text",
			$actual[Wish::PARAM_DESCRIPTION]
		);
		$this->assertSame( '2222-01-23T00:00:00Z', $actual[Wish::PARAM_CREATED] );
	}

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

	public function testTitleOverMaxBytes(): void {
		$title = $this->getExistingTestPage( 'Community Wishlist/Wishes/W123' );
		$wish = new Wish(
			$title,
			'en',
			$this->getTestUser()->getUser(),
			[
				Wish::PARAM_TITLE => str_repeat( 'a', 500 ),
				Wish::PARAM_CREATED => '2025-01-01T00:00:00Z',
			]
		);
		$this->getStore()->save( $wish );
		$wish = $this->getStore()->get( $title );
		$this->assertSame( str_repeat( 'a', 255 ), $wish->getTitle() );
	}
}
