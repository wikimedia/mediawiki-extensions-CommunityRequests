<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Tests\Api\ApiTestCase;
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
	public function testExecute( array $params, array|string $expected ): void {
		User::createNew( 'TestUser' );

		$params = [
			'action' => 'wishedit',
			...$params
		];

		if ( is_string( $expected ) ) {
			$this->expectException( ApiUsageException::class );
			$this->expectExceptionMessage( $expected );
		}
		[ $ret ] = $this->doApiRequestWithToken( $params );

		if ( isset( $expected[ 'warnings' ] ) ) {
			$this->assertArrayEquals( $expected[ 'warnings' ], $ret[ 'warnings' ] );
		} else {
			$this->assertArrayNotHasKey( 'warnings', $ret );
		}
		$expected[ 'wishedit' ] = [
			...$params,
			...$expected[ 'wishedit' ] ?? [],
		];

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
				]
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
				]
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
				]
			],
		];
	}
}
