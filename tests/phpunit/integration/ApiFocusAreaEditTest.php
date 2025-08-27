<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\Api\ApiFocusAreaEdit
 * @covers \MediaWiki\Extension\CommunityRequests\Api\ApiWishlistEntityBase
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks
 */
class ApiFocusAreaEditTest extends ApiTestCase {

	/**
	 * @dataProvider provideTestExecute
	 */
	public function testExecute(
		array $params,
		array|string $expected,
		string $expectedSummary = '',
		?string $expectedUpdateSummary = null
	): void {
		$params['action'] = 'focusareaedit';

		// If $expected is a string, we expect an error message to match it.
		if ( is_string( $expected ) ) {
			$this->expectException( ApiUsageException::class );
			$this->expectExceptionMessage( $expected );
		}

		// Make the request.
		CommunityRequestsHooks::$allowManualEditing = true;
		[ $ret ] = $this->doApiRequestWithToken( $params, null, $this->getTestSysop()->getUser() );

		// If we were asserting an error, we're done.
		if ( is_string( $expected ) ) {
			return;
		}

		// Assert warnings if applicable.
		if ( isset( $expected['warnings'] ) ) {
			$this->assertArrayEquals( $expected['warnings'], $ret['warnings'] );
		} else {
			$this->assertArrayNotHasKey( 'warnings', $ret );
		}

		// To reduce duplication in the test cases, expect back what was given in $params.
		$expected['focusareaedit'] ??= [];
		$expected['focusareaedit'] += $params;

		// Main body of the response.
		$this->assertSame( $expected['focusareaedit']['title'], $ret['focusareaedit']['title'] );
		$this->assertSame( $expected['focusareaedit']['status'], $ret['focusareaedit']['status'] );
		$this->assertSame( $expected['focusareaedit']['description'], $ret['focusareaedit']['description'] );
		$this->assertSame(
			$expected['focusareaedit']['shortdescription'],
			$ret['focusareaedit']['shortdescription']
		);
		$this->assertSame( $expected['focusareaedit']['owners'], $ret['focusareaedit']['owners'] );
		$this->assertSame( $expected['focusareaedit']['volunteers'], $ret['focusareaedit']['volunteers'] );
		$this->assertSame( $expected['focusareaedit']['created'], $ret['focusareaedit']['created'] );
		$this->assertTrue(
			preg_match( '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/', $ret['focusareaedit']['updated'] ) === 1
		);
		$this->assertSame( $expected['focusareaedit']['baselang'], $ret['focusareaedit']['baselang'] );

		// Fetch the revision.
		$revLookup = $this->getServiceContainer()->getRevisionLookup();
		$revision = $revLookup->getRevisionByTitle(
			Title::newFromText( $ret['focusareaedit']['focusarea'] )->toPageIdentity()
		);
		$this->assertSame( $expectedSummary, $revision->getComment()->text );

		// Make an additional edit and assert the edit summary, if applicable.
		if ( $expectedUpdateSummary !== null ) {
			$params['focusarea'] = $ret['focusareaedit']['focusarea'];
			$params['description'] = 'Updated description';
			[ $ret ] = $this->doApiRequestWithToken( $params );
			$this->assertSame( 'Updated description', $ret['focusareaedit']['description'] );
			$revision = $revLookup->getNextRevision( $revision );
			$this->assertSame( $expectedUpdateSummary, $revision->getComment()->text );
		}
	}

	public static function provideTestExecute(): array {
		return [
			'valid focus area' => [
				[
					'status' => 'under-review',
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
						'status' => 'under-review',
						'description' => '[[Test]] {{description}}',
						'shortdescription' => 'Short [[desc]]',
						'owners' => "* Community Tech\n* Editing",
						'volunteers' => "* [[User:Volunteer1]]\n* [[User:Volunteer2]]",
						'created' => '2023-10-01T12:00:00Z',
					]
				],
				'Publishing the focus area "My test focus area"',
				'Updating the focus area "My test focus area"',
			],
			'missing title' => [
				[
					'status' => 'under-review',
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

	public function testExecuteNoPermission(): void {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			'You do not have permission to create or edit focus areas in the Community Wishlist.'
		);
		$this->expectApiErrorCode( 'focusareaedit-nopermission' );
		$this->doApiRequestWithToken( [
			'action' => 'focusareaedit',
			'status' => 'under-review',
			'title' => 'My test focus area',
			'description' => '[[Test]] {{description}}',
			'shortdescription' => 'Short [[desc]]',
			'owners' => "* Community Tech\n* Editing",
			'volunteers' => "* [[User:Volunteer1]]\n* [[User:Volunteer2]]",
			'created' => '2023-10-01T12:00:00Z',
			'baselang' => 'en',
		], null, $this->getTestUser()->getUser() );
	}
}
