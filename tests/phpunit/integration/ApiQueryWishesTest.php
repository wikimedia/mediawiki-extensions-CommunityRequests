<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Tests\Api\ApiTestCase;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\Api\ApiQueryWishes
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractWishlistStore
 */
class ApiQueryWishesTest extends ApiTestCase {

	use WishlistTestTrait;

	public function testExecuteNoWishes(): void {
		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwlang' => 'en',
		] );
		$this->assertSame( [], $ret['query']['communityrequests-wishes'] );
	}

	protected function getStore(): WishStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	public function testExecuteSortByCreated(): void {
		$queryKey = 'communityrequests-wishes';
		$this->createTestWishWithApi( [
			'title' => 'Test Wish 1',
			'created' => '2023-10-01T00:00:00Z',
		] );
		$this->createTestWishWithApi( [
			'title' => 'Test Wish 2',
			'created' => '2023-10-02T00:00:00Z',
		] );
		$this->createTestWishWithApi( [
			'title' => 'Test Wish 3',
			'created' => '2023-10-03T00:00:00Z',
		] );

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwsort' => 'created',
			'crwdir' => 'ascending',
			'crwlang' => 'en',
		] );
		$this->assertCount( 3, $ret['query'][$queryKey] );
		$this->assertSame( 'Test Wish 1', $ret['query'][$queryKey][0]['title'] );
		$this->assertSame( 'Test Wish 2', $ret['query'][$queryKey][1]['title'] );
		$this->assertSame( 'Test Wish 3', $ret['query'][$queryKey][2]['title'] );

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwsort' => 'created',
			'crwdir' => 'descending',
			'crwlang' => 'en',
		] );
		$this->assertCount( 3, $ret['query'][$queryKey] );
		$this->assertSame( 'Test Wish 3', $ret['query'][$queryKey][0]['title'] );
		$this->assertSame( 'Test Wish 2', $ret['query'][$queryKey][1]['title'] );
		$this->assertSame( 'Test Wish 1', $ret['query'][$queryKey][2]['title'] );
	}

	public function testExecuteSortByUpdated(): void {
		$queryKey = 'communityrequests-wishes';
		$this->createTestWishWithApi( [
			'title' => 'Test Wish 1',
			'updated' => '2023-10-01T01:00:00Z',
		] );
		$this->createTestWishWithApi( [
			'title' => 'Test Wish 2',
			'updated' => '2023-10-02T01:00:00Z',
		] );
		$this->createTestWishWithApi( [
			'title' => 'Test Wish 3',
			'updated' => '2023-10-03T01:00:00Z',
		] );

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwsort' => 'updated',
			'crwdir' => 'ascending',
			'crwlang' => 'en',
		] );
		$this->assertCount( 3, $ret['query'][$queryKey] );
		$this->assertSame( 'Test Wish 1', $ret['query'][$queryKey][0]['title'] );
		$this->assertSame( 'Test Wish 2', $ret['query'][$queryKey][1]['title'] );
		$this->assertSame( 'Test Wish 3', $ret['query'][$queryKey][2]['title'] );

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwsort' => 'updated',
			'crwdir' => 'descending',
			'crwlang' => 'en',
		 ] );
		$this->assertCount( 3, $ret['query'][$queryKey] );
		$this->assertSame( 'Test Wish 3', $ret['query'][$queryKey][0]['title'] );
		$this->assertSame( 'Test Wish 2', $ret['query'][$queryKey][1]['title'] );
		$this->assertSame( 'Test Wish 1', $ret['query'][$queryKey][2]['title'] );
	}

	public function testExecuteSortByTitle(): void {
		$queryKey = 'communityrequests-wishes';
		$this->createTestWishWithApi( [
			'title' => 'Test Wish A',
			'created' => '2023-11-01T00:00:00Z',
		] );
		// Make Wish B translatable.
		$wishBPage = $this->createTestWishWithApi( [
			'title' => '<translate>Test Wish B</translate>',
			'created' => '2023-12-01T00:00:00Z',
		] )['wishedit']['wish'];
		$this->markForTranslation( $wishBPage );
		// Insert a translation for wish B, to ensure sorting still works correctly.
		$this->insertTestWish( $wishBPage, 'fr', [
			// Would normally come before "Test Wish A".
			Wish::PARAM_TITLE => 'A French translation',
			Wish::PARAM_BASE_LANG => 'en',
		] );
		$this->createTestWishWithApi( [
			'title' => 'Test Wish C',
			'created' => '2023-09-01T00:00:00Z',
		] );

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwsort' => 'title',
			'crwdir' => 'ascending',
			'crwlang' => 'fr',
			'crlimit' => 3,
		] );
		$this->assertCount( 3, $ret['query'][$queryKey] );
		$this->assertSame( 'A French translation', $ret['query'][$queryKey][0]['title'] );
		$this->assertSame( 'Test Wish A', $ret['query'][$queryKey][1]['title'] );
		$this->assertSame( 'Test Wish C', $ret['query'][$queryKey][2]['title'] );

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwsort' => 'title',
			'crwdir' => 'descending',
			'crwlang' => 'fr',
		] );
		$this->assertCount( 3, $ret['query'][$queryKey] );
		$this->assertSame( 'Test Wish C', $ret['query'][$queryKey][0]['title'] );
		$this->assertSame( 'Test Wish A', $ret['query'][$queryKey][1]['title'] );
		$this->assertSame( 'A French translation', $ret['query'][$queryKey][2]['title'] );
	}

	public function testExecuteWithContinue(): void {
		$this->createTestWishWithApi( [
			'title' => 'Test Wish 1',
			'created' => '2023-10-01T00:00:00Z',
		] );
		$this->createTestWishWithApi( [
			'title' => 'Test Wish 2',
			'created' => '2023-10-02T00:00:00Z',
		] );
		$this->createTestWishWithApi( [
			'title' => 'Test Wish 3',
			'created' => '2023-10-03T00:00:00Z',
		] );

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwsort' => 'created',
			'crwdir' => 'descending',
			'crwlang' => 'en',
			'crwlimit' => 2,
		] );
		$this->assertCount( 2, $ret['query']['communityrequests-wishes'] );
		$this->assertSame( 'Test Wish 3', $ret['query']['communityrequests-wishes'][0]['title'] );
		$this->assertSame( 'Test Wish 2', $ret['query']['communityrequests-wishes'][1]['title'] );
		$this->assertSame( 'Test Wish 1|20231001000000|0', $ret['continue']['crwcontinue'] );

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwsort' => 'created',
			'crwdir' => 'descending',
			'crwlang' => 'en',
			'crwlimit' => 2,
			'crwcontinue' => 'Test Wish 1|20231001000000|2',
		] );
		$this->assertCount( 1, $ret['query']['communityrequests-wishes'] );
		$this->assertSame( 'Test Wish 1', $ret['query']['communityrequests-wishes'][0]['title'] );
		$this->assertArrayNotHasKey( 'continue', $ret );
	}

	public function testExecuteWithCount(): void {
		$this->createTestWishWithApi();
		$this->createTestWishWithApi();

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwcount' => 1,
		] );
		$this->assertSame( 2, $ret['query']['communityrequests-wishes-metadata']['count'] );
	}

	public function testExecuteWithLanguageFallbacks(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Translate' );

		// Create a wish in French.
		$this->insertTestWish(
			'Community Wishlist/Wishes/W1',
			'fr',
			[ Wish::PARAM_TITLE => '<translate>Original French title</translate>' ],
		);
		// Add an English translation.
		$this->insertTestWish(
			'Community Wishlist/Wishes/W1',
			'en',
			[
				Wish::PARAM_TITLE => 'Title in English',
				Wish::PARAM_BASE_LANG => 'fr'
			],
		);

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwlang' => 'fr',
		] );
		$wishesFr = $ret['query']['communityrequests-wishes'];
		$this->assertCount( 1, $wishesFr );
		$this->assertSame( 'Community Wishlist/Wishes/W1', $wishesFr[0]['crwtitle'] );
		$this->assertSame( 'Original French title', $wishesFr[0][Wish::PARAM_TITLE] );

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwlang' => 'en',
		] );
		$wishesEn = $ret['query']['communityrequests-wishes'];
		$this->assertCount( 1, $wishesEn );
		$this->assertSame( 'Community Wishlist/Wishes/W1/en', $wishesEn[0]['crwtitle'] );
		$this->assertSame( 'Title in English', $wishesEn[0][Wish::PARAM_TITLE] );
	}

	public function testExecuteNoTranslateTagsReturned(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Translate' );
		$this->insertTestWish(
			'Community Wishlist/Wishes/W1',
			'en',
			[
				Wish::PARAM_TITLE => '<translate>Translatable title</translate>',
				Wish::PARAM_DESCRIPTION => '<translate>Translatable description</translate>',
				Wish::PARAM_AUDIENCE => '<translate>Translatable audience</translate>',
			],
		);
		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwprop' => 'title|description|audience',
			'crwlang' => 'en',
		] );
		$wish = $ret['query']['communityrequests-wishes'][0];
		$this->assertSame( 'Translatable title', $wish[Wish::PARAM_TITLE] );
		$this->assertSame( 'Translatable description', $wish[Wish::PARAM_DESCRIPTION] );
		$this->assertSame( 'Translatable audience', $wish[Wish::PARAM_AUDIENCE] );
	}

	/**
	 * @dataProvider provideExecuteFilterByTag
	 */
	public function testExecuteFilterByTag( ?string $crwtags, int $count ): void {
		$this->createTestWishWithApi( [ Wish::PARAM_TAGS => 'categories' ] );
		$this->createTestWishWithApi( [ Wish::PARAM_TAGS => 'categories|admins' ] );
		$this->createTestWishWithApi( [ Wish::PARAM_TAGS => 'admins' ] );
		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwtags' => $crwtags,
		] );
		$this->assertCount( $count, $ret['query']['communityrequests-wishes'] );
	}

	public function provideExecuteFilterByTag(): array {
		return [
			[ 'crwtags' => null, 'count' => 3 ],
			[ 'crwtags' => 'categories', 'count' => 2 ],
			[ 'crwtags' => 'categories|admins', 'count' => 3 ],
		];
	}

	/**
	 * @dataProvider provideExecuteFilterByFocusArea
	 */
	public function testExecuteFilterByFocusArea( ?string $crwfocusareas, int $count, bool $exception = false ): void {
		// Two unassigned wishes.
		$this->createTestWishWithApi();
		$this->createTestWishWithApi();
		// One assigned to an existing FA.
		$this->createTestFocusAreaWithApi();
		$this->createTestWishWithApi( [ Wish::PARAM_FOCUS_AREA => 'FA1' ] );
		// One assigned to a non-existing FA.
		$this->createTestWishWithApi( [ Wish::PARAM_FOCUS_AREA => 'FA100' ] );
		if ( $exception ) {
			$this->expectException( ApiUsageException::class );
		}
		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwfocusareas' => $crwfocusareas,
		] );
		$this->assertCount( $count, $ret['query']['communityrequests-wishes'] );
	}

	public function provideExecuteFilterByFocusArea(): array {
		return [
			[ 'crwfocusareas' => null, 'count' => 4 ],
			[ 'crwfocusareas' => 'FA1', 'count' => 1 ],
			[ 'crwfocusareas' => 'FA1|FA100', 'count' => 0, 'exception' => true ],
			[ 'crwfocusareas' => 'FA100', 'count' => 0, 'exception' => true ],
		];
	}

	private function createTestWishWithApi( $params = [] ): array {
		$params = [
			'action' => 'wishedit',
			'status' => $params['status'] ?? 'under-review',
			'title' => $params['title'] ?? 'Test Wish',
			'type' => $params['type'] ?? 'feature',
			'description' => $params['description'] ?? 'This is a test wish.',
			'tags' => $params['tags'] ?? 'admins|multimedia',
			'audience' => $params['audience'] ?? 'everyone',
			'phabtasks' => $params['phabtasks'] ?? 'T123,T456',
			'proposer' => $params['proposer'] ?? $this->getTestUser()->getUser()->getName(),
			'created' => $params['created'] ?? '2023-10-01T00:00:00Z',
			'baselang' => $params['baselang'] ?? 'en',
			...$params,
		];
		CommunityRequestsHooks::$allowManualEditing = true;
		[ $ret ] = $this->doApiRequestWithToken( $params );
		return $ret;
	}

	private function createTestFocusAreaWithApi(): array {
		$params = [
			'action' => 'focusareaedit',
			'status' => 'under-review',
			'title' => 'My test focus area',
			'description' => '[[Test]] {{description}}',
			'shortdescription' => 'Short [[desc]]',
			'owners' => "* Community Tech\n* Editing",
			'volunteers' => "* [[User:Volunteer1]]\n* [[User:Volunteer2]]",
			'created' => '2025-09-11T12:00:00Z',
			'baselang' => 'en',
		];
		CommunityRequestsHooks::$allowManualEditing = true;
		[ $ret ] = $this->doApiRequestWithToken( $params );
		return $ret;
	}
}
