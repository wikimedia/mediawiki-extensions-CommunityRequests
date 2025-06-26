<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Tests\Api\ApiTestCase;

/**
 * @group CommunityRequests
 * @group Database
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\Api\ApiFocusAreaEdit
 */
class ApiFocusAreaEditTest extends ApiTestCase {

	/**
	 * @covers ::execute
	 * @covers ::getEditSummary
	 * @dataProvider provideTestExecute
	 */
	public function testExecute( array $params, array|string $expected ): void {
		$params[ 'action' ] = 'focusareaedit';

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
		$expected[ 'focusareaedit' ] ??= [];
		$expected[ 'focusareaedit' ] += $params;

		$this->assertSame( $expected[ 'focusareaedit' ][ 'title' ], $ret[ 'focusareaedit' ][ 'title' ] );
		$this->assertSame( $expected[ 'focusareaedit' ][ 'status' ], $ret[ 'focusareaedit' ][ 'status' ] );
		$this->assertSame( $expected[ 'focusareaedit' ][ 'description' ], $ret[ 'focusareaedit' ][ 'description' ] );
		$this->assertSame(
			$expected[ 'focusareaedit' ][ 'shortdescription' ],
			$ret[ 'focusareaedit' ][ 'shortdescription' ]
		);
		$this->assertSame( $expected[ 'focusareaedit' ][ 'owners' ], $ret[ 'focusareaedit' ][ 'owners' ] );
		$this->assertSame( $expected[ 'focusareaedit' ][ 'volunteers' ], $ret[ 'focusareaedit' ][ 'volunteers' ] );
		$this->assertSame( $expected[ 'focusareaedit' ][ 'created' ], $ret[ 'focusareaedit' ][ 'created' ] );
		$this->assertTrue(
			preg_match( '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/', $ret[ 'focusareaedit' ][ 'updated' ] ) === 1
		);
		$this->assertSame( $expected[ 'focusareaedit' ][ 'baselang' ], $ret[ 'focusareaedit' ][ 'baselang' ] );
	}

	public static function provideTestExecute(): array {
		return [
			'valid focus area' => [
				[
					'status' => 'draft',
					'title' => 'My test focus area',
					'description' => '[[Test]] {{description}}',
					'shortdescription' => 'Short [[desc]]',
					'owners' => "* Community Tech\n* Editing",
					'volunteers' => "* [[User:Volunteer1]]\n* [[User:Volunteer2]]",
					'created' => '2023-10-01T12:00:00Z',
					'baselang' => 'en',
				],
				[
					'focusareaedit' => [
						'title' => 'My test focus area',
						'status' => 'draft',
						'description' => '[[Test]] {{description}}',
						'shortdescription' => 'Short [[desc]]',
						'owners' => "* Community Tech\n* Editing",
						'volunteers' => "* [[User:Volunteer1]]\n* [[User:Volunteer2]]",
						'created' => '2023-10-01T12:00:00Z',
					]
				]
			],
			'missing title' => [
				[
					'status' => 'draft',
					'title' => '',
					'description' => '[[Test]] {{description}}',
					'shortdescription' => 'Short [[desc]]',
					'owners' => "* Community Tech\n* Editing",
					'volunteers' => "* [[User:Volunteer1]]\n* [[User:Volunteer2]]",
					'created' => '2023-10-01T12:00:00Z',
				],
				'The "title" parameter must be set.'
			],
		];
	}
}
