<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\HookHandler\PermissionHooks;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\Api\ApiWishEdit
 * @covers \MediaWiki\Extension\CommunityRequests\Api\ApiWishlistEntityBase
 * @covers \MediaWiki\Extension\CommunityRequests\Api\ApiWishlistEditBase
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks
 * @covers \MediaWiki\Extension\CommunityRequests\Wish\WishStore
 */
class ApiWishEditTest extends ApiTestCase {
	use WishlistTestTrait;

	protected function getStore(): AbstractWishlistStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	/**
	 * @dataProvider provideTestExecute
	 */
	public function testExecute( array $params, array|string $expected, string $expectedSummary = '' ): void {
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
		PermissionHooks::$allowManualEditing = true;
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
		if ( $ret['wishedit']['baselang'] !== $this->getConfVar( MainConfigNames::LanguageCode ) &&
			$this->getServiceContainer()->getExtensionRegistry()->isLoaded( 'Translate' )
		) {
			$translationLang = $this->getDb()->newSelectQueryBuilder()
				->select( AbstractWishlistStore::translationLangField() )
				->from( AbstractWishlistStore::translationsTableName() )
				->where( [ AbstractWishlistStore::translationForeignKey() => $revTitle->getId() ] )
				->fetchField();
			$this->assertSame( $expected['wishedit']['baselang'], $translationLang );
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
				'Publishing the wish "Test Wish" ([[phab:T123|T123]], [[phab:T456|T456]], [[phab:T789|T789]])'
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
		PermissionHooks::$allowManualEditing = true;

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

	/**
	 * @dataProvider provideGetEditSummary
	 */
	public function testGetEditSummary( array $oldParams, array $newParams, ?string $expectedSummary ): void {
		foreach ( [ $oldParams, $newParams ] as $params ) {
			// Create FAs if needed.
			if ( $params['focusarea'] ?? false ) {
				$pageRef = $this->config->getEntityPageRefFromWikitextVal( $params['focusarea'] );
				$this->insertTestFocusArea( $pageRef->getDBkey() );
			}
			// Create users if needed.
			if ( isset( $params['proposer'] ) ) {
				User::createNew( $params['proposer'] );
			}
			// Create {{gallery}} for testing substitution if needed.
			if ( str_contains( $params['description'] ?? '', 'gallery' ) ) {
				$this->insertPage( 'Template:Gallery', '<gallery></gallery>' );
			}
		}

		$defaultParams = [
			'action' => 'wishedit',
			'status' => 'under-review',
			'focusarea' => '',
			'title' => 'Test Wish',
			'description' => 'This is a test wish.',
			'type' => 'feature',
			'tags' => '',
			'audience' => '',
			'phabtasks' => '',
			'proposer' => $this->getTestSysop()->getUser()->getName(),
			'created' => '2023-10-01T12:00:00Z',
			'baselang' => 'en',
		];
		$oldParams = array_merge( $defaultParams, $oldParams );
		$newParams = array_merge( $defaultParams, $newParams );

		PermissionHooks::$allowManualEditing = true;
		[ $res ] = $this->doApiRequestWithToken( $oldParams );
		$oldWish = $this->getStore()->get(
			Title::newFromText( $res['wishedit']['wish'] ),
			null,
			WishStore::FETCH_WIKITEXT_TRANSLATED
		);
		// If any param value contains <translate>, mark for translation.
		if ( array_filter( $oldParams, static fn ( $v ) => str_contains( (string)$v, '<translate>' ) ) ) {
			if ( !$this->getServiceContainer()->getExtensionRegistry()->isLoaded( 'Translate' ) ) {
				$this->markTestSkipped( 'Translate extension is not installed.' );
			}
			$this->markForTranslation( Title::newFromPageIdentity( $oldWish->getPage() ) );
		}

		$newParams['wish'] = $res['wishedit']['wish'];
		PermissionHooks::$allowManualEditing = true;
		[ $res ] = $this->doApiRequestWithToken( $newParams );

		if ( $res['wishedit']['nochange'] ?? false ) {
			$this->assertNull( $expectedSummary, 'Expected no edit summary for no-change edit' );
			return;
		}
		$revision = $this->getServiceContainer()
			->getRevisionLookup()
			->getRevisionById( $res['wishedit']['newrevid'] );
		$this->assertSame( $expectedSummary, $revision->getComment()->text );
	}

	public static function provideGetEditSummary(): array {
		return [
			'title change' => [
				[ 'title' => 'Old Title' ],
				[ 'title' => 'New Title' ],
				'Changed title from "Old Title" to "New Title"'
			],
			'status change' => [
				[ 'status' => 'under-review' ],
				[ 'status' => 'prioritized' ],
				'Changed status from "Under review" to "Prioritized"'
			],
			'description change' => [
				[ 'description' => 'Old description.' ],
				[ 'description' => 'New description.' ],
				'Updated description'
			],
			'type change' => [
				[ 'type' => 'feature' ],
				[ 'type' => 'bug' ],
				'Changed type from "Feature request" to "Bug report"'
			],
			'focus area change' => [
				[ 'focusarea' => 'FA1' ],
				[ 'focusarea' => 'FA2' ],
				'Changed focus area from "[[Community_Wishlist/FA1|FA1]]" to "[[Community_Wishlist/FA2|FA2]]"'
			],
			'new focus area' => [
				[ 'focusarea' => '' ],
				[ 'focusarea' => 'FA2' ],
				'Changed focus area from "Unassigned" to "[[Community_Wishlist/FA2|FA2]]"'
			],
			'tags change' => [
				[ 'tags' => '' ],
				[ 'tags' => 'multimedia|reading' ],
				'Added tags: Multimedia and Commons, Reading'
			],
			'audience change' => [
				[ 'audience' => 'New editors' ],
				[ 'audience' => 'Experienced editors' ],
				'Updated affected users'
			],
			'phabtasks change' => [
				[ 'phabtasks' => 'T123|T456' ],
				[ 'phabtasks' => 'T789' ],
				'Added Phabricator task: [[phab:T789|T789]]; ' .
					'Removed Phabricator tasks: [[phab:T123|T123]], [[phab:T456|T456]]'
			],
			'proposer change' => [
				[ 'proposer' => 'OldUser' ],
				[ 'proposer' => 'NewUser' ],
				'Changed proposer from "OldUser" to "NewUser"'
			],
			'bunch of changes' => [
				[
					'title' => 'Old Title',
					'focusarea' => 'FA1',
					'description' => 'Old description.',
					'status' => 'under-review',
					'type' => 'feature',
					'tags' => 'multimedia|patrolling',
				],
				[
					'title' => 'New Title',
					'focusarea' => '',
					'description' => 'New description.',
					'status' => 'in-progress',
					'type' => 'bug',
					'tags' => 'reading|wikidata',
				],
				'Changed title from "Old Title" to "New Title"; ' .
					'Updated description; Changed status from "Under review" to "In progress"; ' .
					'Changed type from "Feature request" to "Bug report"; ' .
					'Changed focus area from "[[Community_Wishlist/FA1|FA1]]" to "Unassigned"; ' .
					'Added tags: Reading, Wikidata; Removed tags: Multimedia and Commons, Patrolling'
			],
			'with translations' => [
				[ 'title' => '<translate>Old title</translate>' ],
				[ 'title' => '<translate>New title</translate>' ],
				'Changed title from "Old title" to "New title"'
			],
			'pre-save transformation + translation' => [
				[ 'description' => "<translate>\n<!--T:1--> Line 1.\n\n<!--T:2--> " .
					"Line 2.\n{{subst:gallery}} ~~~</translate>" ],
				[ 'description' => "<translate>\r\n<!--T:1--> Line 1.\r\n\r\n" .
					"<!--T:2--> Line 2.\r\n<gallery></gallery> " .
					'[[User:UTSysop|UTSysop]] ([[User talk:UTSysop|talk]])</translate>' ],
				// XXX: Should be null (API return 'nochage'), but isn't here in the tests for some reason.
				'',
			],
			'no changes' => [
				[],
				[],
				null,
			],
		];
	}

	/**
	 * Test that a vote is added for the proposer when a new wish is created.
	 *
	 * @return void
	 */
	public function testProposerVoteIsSaved(): void {
		PermissionHooks::$allowManualEditing = true;
		$user = $this->getTestUser()->getUser();
		$params = [
			'action' => 'wishedit',
			Wish::PARAM_TITLE => 'Test Wish',
			Wish::PARAM_DESCRIPTION => 'This is a test wish.',
			Wish::PARAM_AUDIENCE => 'everyone',
			Wish::PARAM_STATUS => 'under-review',
			Wish::PARAM_TYPE => 'change',
			Wish::PARAM_TAGS => '',
			Wish::PARAM_PHAB_TASKS => '',
			Wish::PARAM_CREATED => '2025-10-21T00:00:00Z',
			Wish::PARAM_PROPOSER => $user->getName(),
			Wish::PARAM_BASE_LANG => 'en',
		];
		[ $ret ] = $this->doApiRequestWithToken( $params, null, $user );
		$this->assertSame( 'Community Wishlist/W1', $ret['wishedit']['wish'] );
		$this->assertArrayHasKey( 'wishlistvote', $ret );
		$votesPage = Title::newFromText( 'Community Wishlist/W1/Votes' );
		$this->assertTrue( $votesPage->exists() );
	}
}
