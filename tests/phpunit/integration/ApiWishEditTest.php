<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * @group CommunityRequests
 * @group Database
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\Api\ApiWishEdit
 */
class ApiWishEditTest extends ApiTestCase {

	/**
	 * @covers ::execute
	 * @covers ::getEditSummary
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

		$params[ 'action' ] = 'wishedit';

		// If $expected is a string, we expect an error message to match it.
		if ( is_string( $expected ) ) {
			$this->expectException( ApiUsageException::class );
			$this->expectExceptionMessage( $expected );
			$this->expectApiErrorCode( 'missingparam' );
		}

		// Make the request.
		[ $ret ] = $this->doApiRequestWithToken( $params );

		// If we were asserting an error, we're done.
		if ( is_string( $expected ) ) {
			return;
		}

		// Assert warnings if applicable.
		if ( isset( $expected[ 'warnings' ] ) ) {
			$this->assertArrayEquals( $expected[ 'warnings' ], $ret[ 'warnings' ] );
		} else {
			$this->assertArrayNotHasKey( 'warnings', $ret );
		}

		// To reduce duplication in the test cases, expect back what was given in $params.
		$expected[ 'wishedit' ] ??= [];
		$expected[ 'wishedit' ] += $params;

		// Main body of the response.
		$this->assertSame( $expected[ 'wishedit' ][ 'title' ], $ret[ 'wishedit' ][ 'title' ] );
		$this->assertSame( $expected[ 'wishedit' ][ 'status' ], $ret[ 'wishedit' ][ 'status' ] );
		$this->assertSame( $expected[ 'wishedit' ][ 'type' ], $ret[ 'wishedit' ][ 'type' ] );
		$this->assertSame( $expected[ 'wishedit' ][ 'focusarea' ], $ret[ 'wishedit' ][ 'focusarea' ] );
		$this->assertSame( $expected[ 'wishedit' ][ 'description' ], $ret[ 'wishedit' ][ 'description' ] );
		$this->assertSame( $expected[ 'wishedit' ][ 'projects' ], $ret[ 'wishedit' ][ 'projects' ] );
		$this->assertSame( $expected[ 'wishedit' ][ 'otherproject' ], $ret[ 'wishedit' ][ 'otherproject' ] );
		$this->assertSame( $expected[ 'wishedit' ][ 'audience' ], $ret[ 'wishedit' ][ 'audience' ] );
		$this->assertSame( $expected[ 'wishedit' ][ 'phabtasks' ], $ret[ 'wishedit' ][ 'phabtasks' ] );
		$this->assertSame( $expected[ 'wishedit' ][ 'proposer' ], $ret[ 'wishedit' ][ 'proposer' ] );
		$this->assertSame( $expected[ 'wishedit' ][ 'created' ], $ret[ 'wishedit' ][ 'created' ] );
		$this->assertTrue(
			preg_match( '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/', $ret[ 'wishedit' ][ 'updated' ] ) === 1
		);
		$this->assertSame( $expected[ 'wishedit' ][ 'baselang' ], $ret[ 'wishedit' ][ 'baselang' ] );

		// Fetch the revision.
		$revLookup = $this->getServiceContainer()->getRevisionLookup();
		$revision = $revLookup->getRevisionByTitle(
			Title::newFromText( $ret[ 'wishedit' ][ 'wish' ] )->toPageIdentity()
		);
		$this->assertSame( $expectedSummary, $revision->getComment()->text );

		// Make an additional edit and assert the edit summary, if applicable.
		if ( $expectedUpdateSummary !== null ) {
			$params[ 'wish' ] = $ret[ 'wishedit' ][ 'wish' ];
			$params[ 'description' ] = 'Updated description';
			[ $ret ] = $this->doApiRequestWithToken( $params );
			$this->assertSame( 'Updated description', $ret[ 'wishedit' ][ 'description' ] );
			$revision = $revLookup->getNextRevision( $revision );
			$this->assertSame( $expectedUpdateSummary, $revision->getComment()->text );
		}
	}

	public static function provideTestExecute(): array {
		return [
			'invalid project' => [
				[
					'status' => 'submitted',
					'focusarea' => '',
					'title' => 'Test Wish',
					'description' => 'This is a test wish.',
					'type' => 'feature',
					'projects' => 'bogus|commons',
					'otherproject' => '',
					'audience' => 'Experienced editors',
					'phabtasks' => 'T123|T456|T789',
					'proposer' => 'TestUser',
					'created' => '2023-10-01T12:00:00Z',
					'baselang' => 'en',
				],
				[
					'warnings' => [
						'wishedit' => [
							'warnings' => 'Unrecognized value for parameter "projects": bogus',
						]
					],
					'wishedit' => [
						'focusarea' => null,
						'projects' => [ 'commons' ],
						'phabtasks' => [ 'T123', 'T456', 'T789' ],
						'new' => true,
					]
				],
				'Publishing the wish "Test Wish" ([[phab:T123|T123]], [[phab:T456|T456]], [[phab:T789|T789]])',
				'Updating the wish "Test Wish" ([[phab:T123|T123]], [[phab:T456|T456]], [[phab:T789|T789]])'
			],
			'missing proposer' => [
				[
					'status' => 'submitted',
					'focusarea' => '',
					'title' => 'Test Wish',
					'description' => 'This is a test wish.',
					'type' => 'feature',
					'projects' => 'commons|wikidata',
					'otherproject' => '',
					'audience' => 'Experienced editors',
					'phabtasks' => 'T123|T456|T789',
					// No proposer
					'created' => '2023-10-01T12:00:00Z',
					'baselang' => 'en',
				],
				'The "proposer" parameter must be set.'
			],
			'all projects' => [
				[
					'status' => 'submitted',
					'focusarea' => '',
					'title' => 'Test Wish',
					'description' => 'This is a test wish.',
					'type' => 'feature',
					'projects' => 'all',
					'otherproject' => '',
					'audience' => 'Experienced editors',
					'phabtasks' => 'T123|T456|T789',
					'proposer' => 'TestUser',
					'created' => '2023-10-01T12:00:00Z',
					'baselang' => 'en',
				],
				[
					'wishedit' => [
						'focusarea' => null,
						'projects' => [ 'all' ],
						'phabtasks' => [ 'T123', 'T456', 'T789' ],
						'new' => true,
					]
				],
				'Publishing the wish "Test Wish" ([[phab:T123|T123]], [[phab:T456|T456]], [[phab:T789|T789]])'
			],
			'no tasks' => [
				[
					'status' => 'submitted',
					'focusarea' => '',
					'title' => 'Test Wish',
					'description' => 'This is a test wish.',
					'type' => 'feature',
					'projects' => 'commons|wikidata',
					'otherproject' => '',
					'audience' => 'Experienced editors',
					// No tasks
					'proposer' => 'TestUser',
					'created' => '2023-10-01T12:00:00Z',
					'baselang' => 'en',
				],
				[
					'wishedit' => [
						'focusarea' => null,
						'projects' => [ 'commons', 'wikidata' ],
						'audience' => 'Experienced editors',
						'phabtasks' => [],
						'new' => true,
					]
				],
				'Publishing the wish "Test Wish"'
			],
		];
	}
}
