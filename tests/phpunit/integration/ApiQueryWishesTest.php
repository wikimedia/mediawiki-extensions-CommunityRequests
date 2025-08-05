<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Tests\Api\ApiTestCase;

/**
 * @group CommunityRequests
 * @group Database
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\Api\ApiQueryWishes
 */
class ApiQueryWishesTest extends ApiTestCase {

	/**
	 * @covers ::execute
	 */
	public function testExecuteNoWishes(): void {
		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwlang' => 'en',
		] );
		$this->assertSame( [], $ret[ 'query' ][ 'communityrequests-wishes' ] );
	}

	/**
	 * @covers ::execute
	 * @covers \MediaWiki\Extension\CommunityRequests\AbstractWishlistStore::getAll
	 */
	public function testExecuteSortByCreated(): void {
		$queryKey = 'communityrequests-wishes';
		$this->createTestWish( [
			'title' => 'Test Wish 1',
			'created' => '2023-10-01T00:00:00Z',
		] );
		$this->createTestWish( [
			'title' => 'Test Wish 2',
			'created' => '2023-10-02T00:00:00Z',
		] );
		$this->createTestWish( [
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
		$this->assertCount( 3, $ret[ 'query' ][ $queryKey ] );
		$this->assertSame( 'Test Wish 1', $ret[ 'query' ][ $queryKey ][ 0 ][ 'title' ] );
		$this->assertSame( 'Test Wish 2', $ret[ 'query' ][ $queryKey ][ 1 ][ 'title' ] );
		$this->assertSame( 'Test Wish 3', $ret[ 'query' ][ $queryKey ][ 2 ][ 'title' ] );

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwsort' => 'created',
			'crwdir' => 'descending',
			'crwlang' => 'en',
		] );
		$this->assertCount( 3, $ret[ 'query' ][ $queryKey ] );
		$this->assertSame( 'Test Wish 3', $ret[ 'query' ][ $queryKey ][ 0 ][ 'title' ] );
		$this->assertSame( 'Test Wish 2', $ret[ 'query' ][ $queryKey ][ 1 ][ 'title' ] );
		$this->assertSame( 'Test Wish 1', $ret[ 'query' ][ $queryKey ][ 2 ][ 'title' ] );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecuteSortByUpdated(): void {
		$queryKey = 'communityrequests-wishes';
		$this->createTestWish( [
			'title' => 'Test Wish 1',
			'updated' => '2023-10-01T01:00:00Z',
		] );
		$this->createTestWish( [
			'title' => 'Test Wish 2',
			'updated' => '2023-10-02T01:00:00Z',
		] );
		$this->createTestWish( [
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
		$this->assertCount( 3, $ret[ 'query' ][ $queryKey ] );
		$this->assertSame( 'Test Wish 1', $ret[ 'query' ][ $queryKey ][ 0 ][ 'title' ] );
		$this->assertSame( 'Test Wish 2', $ret[ 'query'][ $queryKey ][ 1 ][ 'title'] );
		$this->assertSame( 'Test Wish 3', $ret[ 'query'][ $queryKey ][ 2 ][ 'title'] );

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwsort' => 'updated',
			'crwdir' => 'descending',
			'crwlang' => 'en',
		 ] );
		$this->assertCount( 3, $ret[ 'query'][ $queryKey ] );
		$this->assertSame( 'Test Wish 3', $ret[ 'query'][ $queryKey ][ 0 ][ 'title'] );
		$this->assertSame( 'Test Wish 2', $ret[ 'query'][ $queryKey ][ 1 ][ 'title'] );
		$this->assertSame( 'Test Wish 1', $ret[ 'query'][ $queryKey ][ 2 ][ 'title'] );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecuteSortByTitle(): void {
		$queryKey = 'communityrequests-wishes';
		$this->createTestWish( [
			'title' => 'Test Wish A',
			'created' => '2023-11-01T00:00:00Z',
		] );
		$this->createTestWish( [
			'title' => 'Test Wish B',
			'created' => '2023-12-01T00:00:00Z',
		] );
		$this->createTestWish( [
			'title' => 'Test Wish C',
			'created' => '2023-09-01T00:00:00Z',
		] );

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwsort' => 'title',
			'crwdir' => 'ascending',
			'crwlang' => 'en',
		] );
		$this->assertCount( 3, $ret[ 'query' ][ $queryKey ] );
		$this->assertSame( 'Test Wish A', $ret[ 'query' ][ $queryKey ][ 0 ][ 'title' ] );
		$this->assertSame( 'Test Wish B', $ret[ 'query' ][ $queryKey ][ 1 ][ 'title' ] );
		$this->assertSame( 'Test Wish C', $ret[ 'query' ][ $queryKey ][ 2 ][ 'title' ] );

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwsort' => 'title',
			'crwdir' => 'descending',
			'crwlang' => 'en',
		] );
		$this->assertCount( 3, $ret[ 'query' ][ $queryKey ] );
		$this->assertSame( 'Test Wish C', $ret[ 'query' ][ $queryKey ][ 0 ][ 'title' ] );
		$this->assertSame( 'Test Wish B', $ret[ 'query' ][ $queryKey ][ 1 ][ 'title' ] );
		$this->assertSame( 'Test Wish A', $ret[ 'query' ][ $queryKey ][ 2 ][ 'title' ] );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecuteWithContinue(): void {
		$this->createTestWish( [
			'title' => 'Test Wish 1',
			'created' => '2023-10-01T00:00:00Z',
		] );
		$this->createTestWish( [
			'title' => 'Test Wish 2',
			'created' => '2023-10-02T00:00:00Z',
		] );
		$this->createTestWish( [
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
		$this->assertCount( 2, $ret[ 'query' ][ 'communityrequests-wishes' ] );
		$this->assertSame( 'Test Wish 3', $ret[ 'query' ][ 'communityrequests-wishes' ][ 0 ][ 'title' ] );
		$this->assertSame( 'Test Wish 2', $ret[ 'query' ][ 'communityrequests-wishes' ][ 1 ][ 'title' ] );
		$this->assertSame( 'Test Wish 1|20231001000000|0', $ret[ 'continue' ][ 'crwcontinue' ] );

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwsort' => 'created',
			'crwdir' => 'descending',
			'crwlang' => 'en',
			'crwlimit' => 2,
			'crwcontinue' => 'Test Wish 1|20231001000000|2',
		] );
		$this->assertCount( 1, $ret[ 'query' ][ 'communityrequests-wishes' ] );
		$this->assertSame( 'Test Wish 1', $ret[ 'query' ][ 'communityrequests-wishes' ][ 0 ][ 'title' ] );
		$this->assertArrayNotHasKey( 'continue', $ret );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecuteWithCount(): void {
		$this->createTestWish();
		$this->createTestWish();

		[ $ret ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'communityrequests-wishes',
			'crwcount' => 1,
		] );
		$this->assertSame( 2, $ret[ 'query' ][ 'communityrequests-wishes-metadata' ][ 'count' ] );
	}

	private function createTestWish( $params = [] ): array {
		$params = [
			'action' => 'wishedit',
			'status' => $params[ 'status' ] ?? 'under-review',
			'title' => $params[ 'title' ] ?? 'Test Wish',
			'type' => $params[ 'type' ] ?? 'feature',
			'description' => $params[ 'description' ] ?? 'This is a test wish.',
			'projects' => $params[ 'projects' ] ?? 'wikipedia|commons',
			'otherproject' => $params[ 'otherproject' ] ?? '',
			'audience' => $params[ 'audience' ] ?? 'everyone',
			'phabtasks' => $params[ 'phabtasks' ] ?? 'T123,T456',
			'proposer' => $params[ 'proposer' ] ?? $this->getTestUser()->getUser()->getName(),
			'created' => $params[ 'created' ] ?? '2023-10-01T00:00:00Z',
			'baselang' => $params[ 'baselang' ] ?? 'en',
			...$params,
		];

		[ $ret ] = $this->doApiRequestWithToken( $params );
		return $ret;
	}
}
