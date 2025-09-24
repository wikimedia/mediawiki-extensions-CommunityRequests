<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\Api\ApiWishEdit
 * @covers \MediaWiki\Extension\CommunityRequests\Api\ApiWishlistEntityBase
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks
 */
class ApiWishEditTest extends ApiTestCase {
	use WishlistTestTrait;

	protected function getStore(): AbstractWishlistStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	/**
	 * @dataProvider provideTestExecute
	 */
	public function testExecute(
		array $params,
		array|string $expected,
		string $expectedSummary = '',
		?string $expectedUpdateSummary = null
	): void {
		// Ensure we have a user to work with.
		User::createNew( 'TestUser' );

		$params['action'] = 'wishedit';

		// If $expected is a string, we expect an error message to match it.
		if ( is_string( $expected ) ) {
			$this->expectException( ApiUsageException::class );
			$this->expectExceptionMessage( $expected );
			$this->expectApiErrorCode( 'missingparam' );
		}

		// Make the request.
		CommunityRequestsHooks::$allowManualEditing = true;
		[ $ret ] = $this->doApiRequestWithToken( $params );

		// If we were asserting an error, we're done.
		if ( is_string( $expected ) ) {
			return;
		}

		// Assert warnings if applicable.
		if ( isset( $expected['warnings'] ) ) {
			$this->assertArrayEquals( $expected['warnings' ], $ret['warnings' ] );
		} else {
			$this->assertArrayNotHasKey( 'warnings', $ret );
		}

		// To reduce duplication in the test cases, expect back what was given in $params.
		$expected['wishedit'] ??= [];
		$expected['wishedit'] += $params;

		// Main body of the response. Optional parameters have a blank string as the default.
		$this->assertSame( $expected['wishedit']['title'], $ret['wishedit']['title'] );
		$this->assertSame( $expected['wishedit']['status'], $ret['wishedit']['status'] );
		$this->assertSame( $expected['wishedit']['type'], $ret['wishedit']['type'] );
		$this->assertSame( $expected['wishedit']['focusarea'] ?? '', $ret['wishedit']['focusarea'] ?? '' );
		$this->assertSame( $expected['wishedit']['description'], $ret['wishedit']['description'] );
		$this->assertSame( $expected['wishedit']['tags'] ?? '', $ret['wishedit']['tags'] ?? '' );
		$this->assertSame( $expected['wishedit']['audience'] ?? '', $ret['wishedit']['audience'] ?? '' );
		$this->assertSame( $expected['wishedit']['phabtasks'] ?? '', $ret['wishedit']['phabtasks'] ?? '' );
		$this->assertSame( $expected['wishedit']['proposer'], $ret['wishedit']['proposer'] );
		$this->assertSame( $expected['wishedit']['created'], $ret['wishedit']['created'] );
		$this->assertTrue(
			preg_match( '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/', $ret['wishedit']['updated'] ) === 1
		);
		$this->assertSame( $expected['wishedit']['baselang'], $ret['wishedit']['baselang'] );

		// Fetch the revision.
		$revLookup = $this->getServiceContainer()->getRevisionLookup();
		$revTitle = Title::newFromText( $ret['wishedit']['wish'] );
		$revision = $revLookup->getRevisionByTitle( $revTitle->toPageIdentity() );
		$this->assertSame( $expectedSummary, $revision->getComment()->text );

		// If the baselang is not the site language, we expect translations to be stored in that language.
		if ( $ret['wishedit']['baselang'] !== $this->getConfVar( MainConfigNames::LanguageCode ) ) {
			$translationLang = $this->getDb()->newSelectQueryBuilder()
				->select( AbstractWishlistStore::translationLangField() )
				->from( AbstractWishlistStore::translationsTableName() )
				->where( [ AbstractWishlistStore::translationForeignKey() => $revTitle->getId() ] )
				->fetchField();
			$this->assertSame( $expected['wishedit']['baselang'], $translationLang );
		}

		// Make an additional edit and assert the edit summary, if applicable.
		if ( $expectedUpdateSummary !== null ) {
			$params['wish'] = $ret['wishedit']['wish'];
			$params['description'] = 'Updated description';
			[ $ret ] = $this->doApiRequestWithToken( $params );
			$this->assertSame( 'Updated description', $ret['wishedit']['description'] );
			$revision = $revLookup->getNextRevision( $revision );
			$this->assertSame( $expectedUpdateSummary, $revision->getComment()->text );
		}
	}

	public static function provideTestExecute(): array {
		return [
			'invalid tag' => [
				[
					'status' => 'under-review',
					'focusarea' => '',
					'title' => 'Test Wish',
					'description' => 'This is a test wish.',
					'type' => 'feature',
					'tags' => 'bogus|multimedia',
					'audience' => 'Experienced editors',
					'phabtasks' => 'T123|T456|T789',
					'proposer' => 'TestUser',
					'created' => '2023-10-01T12:00:00Z',
					'baselang' => 'en',
				],
				[
					'warnings' => [
						'wishedit' => [
							'warnings' => 'Unrecognized value for parameter "tags": bogus',
						]
					],
					'wishedit' => [
						'focusarea' => '',
						'tags' => [ 'multimedia' ],
						'phabtasks' => [ 'T123', 'T456', 'T789' ],
						'new' => true,
					]
				],
				'Publishing the wish "Test Wish" ([[phab:T123|T123]], [[phab:T456|T456]], [[phab:T789|T789]])',
				'Updating the wish "Test Wish" ([[phab:T123|T123]], [[phab:T456|T456]], [[phab:T789|T789]])'
			],
			'missing proposer' => [
				[
					'status' => 'under-review',
					'focusarea' => '',
					'title' => 'Test Wish',
					'description' => 'This is a test wish.',
					'type' => 'feature',
					'tags' => 'multimedia|wikidata',
					'audience' => 'Experienced editors',
					'phabtasks' => 'T123|T456|T789',
					// No proposer
					'created' => '2023-10-01T12:00:00Z',
					'baselang' => 'en',
				],
				'The "proposer" parameter must be set.'
			],
			'no tasks' => [
				[
					'status' => 'under-review',
					'focusarea' => '',
					'title' => 'Test Wish',
					'description' => 'This is a test wish.',
					'type' => 'feature',
					'tags' => 'multimedia|wikidata',
					'audience' => 'Experienced editors',
					// No tasks
					'proposer' => 'TestUser',
					'created' => '2023-10-01T12:00:00Z',
					'baselang' => 'en',
				],
				[
					'wishedit' => [
						'focusarea' => '',
						'tags' => [ 'multimedia', 'wikidata' ],
						'audience' => 'Experienced editors',
						'phabtasks' => [],
						'new' => true,
					]
				],
				'Publishing the wish "Test Wish"'
			],
			'new wish not in site language' => [
				[
					'status' => 'under-review',
					'focusarea' => '',
					'title' => 'Test Wish',
					'description' => 'This is a test wish.',
					'type' => 'feature',
					'tags' => 'multimedia|wikidata',
					'audience' => 'Experienced editors',
					'proposer' => 'TestUser',
					'created' => '2023-10-01T12:00:00Z',
					// Baselang is not the site language (en)
					'baselang' => 'es',
				],
				[
					'wishedit' => [
						'title' => 'Test Wish',
						'description' => 'This is a test wish.',
						'type' => 'feature',
						'audience' => 'Experienced editors',
						// Other parameters are as expected
						'focusarea' => '',
						'tags' => [ 'multimedia', 'wikidata' ],
						'phabtasks' => [],
						'new' => true,
						'baselang' => 'es',
					]
				],
				"Publishing the wish \"Test Wish\""
			],
			'no tags' => [
				[
					'status' => 'under-review',
					'title' => 'Test Wish',
					'description' => 'This is a test wish.',
					'type' => 'feature',
					// No tags
					'proposer' => 'TestUser',
					'created' => '2023-10-01T12:00:00Z',
					'baselang' => 'en',
				],
				[
					'wishedit' => [
						'focusarea' => '',
						'tags' => [],
						'phabtasks' => [],
						'new' => true,
						'baselang' => 'en',
					]
				],
				'Publishing the wish "Test Wish"'
			]
		];
	}

	public function testExecuteParsingFailure(): void {
		// Ensure we have a user to work with.
		User::createNew( 'TestUser' );

		$params = [
			'action' => 'wishedit',
			'status' => 'under-review',
			'title' => 'Test Wish',
			'description' => 'This is a | test wish with stray pipes',
			'type' => 'feature',
			'tags' => 'multimedia|wikidata',
			'phabtasks' => 'T123|T456|T789',
			'proposer' => 'TestUser',
			'created' => '2023-10-01T12:00:00Z',
			'baselang' => 'en',
		];
		$this->expectApiErrorCode( 'wishlist-entity-parse' );
		$this->doApiRequestWithToken( $params );
	}

	public function testExecuteParsingFailureAndIdGeneration(): void {
		// Ensure we have a user to work with.
		User::createNew( 'TestUser' );
		CommunityRequestsHooks::$allowManualEditing = true;

		// Create an initial valid wish with the API to ensure the ID generation increments.
		$params = [
			'action' => 'wishedit',
			'status' => 'under-review',
			'title' => 'Initial Wish',
			'description' => 'This is a test wish.',
			'type' => 'feature',
			'proposer' => 'TestUser',
			'created' => '2023-10-01T12:00:00Z',
			'baselang' => 'en',
		];
		[ $ret ] = $this->doApiRequestWithToken( $params );
		$this->assertSame( 'Community Wishlist/W1', $ret['wishedit']['wish'] );

		// Now attempt to create a wish that will fail parsing due to stray pipe characters.
		$params = [
			'action' => 'wishedit',
			'status' => 'under-review',
			'title' => 'Test Wish 2',
			'description' => 'This is a | test wish with stray pipes',
			'type' => 'feature',
			'proposer' => 'TestUser',
			'created' => '2023-10-01T12:00:00Z',
			'baselang' => 'en',
		];

		try {
			$this->doApiRequestWithToken( $params );
		} catch ( ApiUsageException ) {
			// Expected exception due to parsing failure.
		}

		// Create another valid wish and ensure the ID has incremented correctly.
		$params = [
			'action' => 'wishedit',
			'status' => 'under-review',
			'title' => 'Another Wish',
			'description' => 'This is another test wish.',
			'type' => 'feature',
			'proposer' => 'TestUser',
			'created' => '2023-10-01T12:00:00Z',
			'baselang' => 'en',
		];
		[ $ret ] = $this->doApiRequestWithToken( $params );
		$this->assertSame( 'Community Wishlist/W2', $ret['wishedit']['wish'] );
	}
}
