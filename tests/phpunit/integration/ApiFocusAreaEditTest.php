<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\HookHandler\PermissionHooks;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;

/**
 * @group CommunityRequests
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\Api\ApiFocusAreaEdit
 * @covers \MediaWiki\Extension\CommunityRequests\Api\ApiWishlistEntityBase
 * @covers \MediaWiki\Extension\CommunityRequests\Api\ApiWishlistEditBase
 * @covers \MediaWiki\Extension\CommunityRequests\ChangesProcessorFactory
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractChangesProcessor
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks
 * @covers \MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaChangesProcessor
 * @covers \MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore
 */
class ApiFocusAreaEditTest extends ApiTestCase {
	use WishlistTestTrait;

	protected function getStore(): AbstractWishlistStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.FocusAreaStore' );
	}

	/**
	 * @dataProvider provideTestExecute
	 */
	public function testExecute( array $params, array|string $expected, string $expectedSummary = '' ): void {
		$params['action'] = 'focusareaedit';

		// If $expected is a string, we expect an error message to match it.
		if ( is_string( $expected ) ) {
			$this->expectException( ApiUsageException::class );
			$this->expectExceptionMessage( $expected );
		}

		// Make the request.
		PermissionHooks::$allowManualEditing = true;
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
			"You don't have permission to manage the Community Wishlist without restrictions."
		);
		$this->expectApiErrorCode( 'permissiondenied' );
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

	/**
	 * @dataProvider provideTestExecuteParsingFailure
	 */
	public function testExecuteParsingFailure( $params ): void {
		$params = array_merge( [
			'action' => 'focusareaedit',
			'status' => 'under-review',
			'title' => 'My test focus area',
			'description' => 'This is a valid description',
			'shortdescription' => 'This is a test short desc',
			'owners' => "* Community Tech\n* Editing",
			'volunteers' => "* [[User:Volunteer1]]\n* [[User:Volunteer2]]",
			'created' => '2023-10-01T12:00:00Z',
			'baselang' => 'en',
		], $params );
		$this->expectApiErrorCode( 'wishlist-entity-parse' );
		$this->doApiRequestWithToken( $params );
	}

	public static function provideTestExecuteParsingFailure(): array {
		return [
			'stray pipe in shortdescription' => [
				[ 'shortdescription' => 'This is a | test short desc with stray pipes' ]
			],
			'stray pipe in owners' => [
				[ 'owners' => "* [[Community Tech|Community" ]
			],
		];
	}

	/**
	 * @dataProvider provideGetEditSummary
	 */
	public function testGetEditSummary( array $oldParams, array $newParams, string $expectedSummary ): void {
		$defaultParams = [
			'action' => 'focusareaedit',
			'status' => 'under-review',
			'title' => 'Test Focus Area',
			'description' => 'This is a test focus area.',
			'shortdescription' => 'This is a test short desc',
			'owners' => "* Community Tech\n* Editing",
			'volunteers' => "* [[User:Volunteer1]]\n* [[User:Volunteer2]]",
			'created' => '2023-10-01T12:00:00Z',
			'baselang' => 'en',
		];
		$oldParams = array_merge( $defaultParams, $oldParams );
		$newParams = array_merge( $defaultParams, $newParams );

		PermissionHooks::$allowManualEditing = true;
		[ $res ] = $this->doApiRequestWithToken( $oldParams );
		$oldFocusArea = $this->getStore()->get(
			Title::newFromText( $res['focusareaedit']['focusarea'] ),
			null,
			FocusAreaStore::FETCH_WIKITEXT_TRANSLATED
		);
		// If any param value contains <translate>, mark for translation.
		if ( array_filter( $oldParams, static fn ( $v ) => str_contains( (string)$v, '<translate>' ) ) ) {
			if ( !$this->getServiceContainer()->getExtensionRegistry()->isLoaded( 'Translate' ) ) {
				$this->markTestSkipped( 'Translate extension is not installed.' );
			}
			$this->markForTranslation( Title::newFromPageIdentity( $oldFocusArea->getPage() ) );
		}

		$newParams['focusarea'] = $res['focusareaedit']['focusarea'];
		PermissionHooks::$allowManualEditing = true;
		[ $res ] = $this->doApiRequestWithToken( $newParams );

		$revision = $this->getServiceContainer()
			->getRevisionLookup()
			->getRevisionById( $res['focusareaedit']['newrevid'] );
		$this->assertSame( $expectedSummary, $revision->getComment()->text );
	}

	public static function provideGetEditSummary(): array {
		return [
			'title changes' => [
				[ 'title' => 'Old Title' ],
				[ 'title' => 'New Title' ],
				'Changed title from "Old Title" to "New Title"',
			],
			'status changes' => [
				[ 'status' => 'under-review' ],
				[ 'status' => 'prioritized' ],
				'Changed status from "Under review" to "Prioritized"',
			],
			'description changes' => [
				[ 'description' => 'Old description.' ],
				[ 'description' => 'New description.' ],
				'Updated description',
			],
			'shortdescription changes' => [
				[ 'shortdescription' => 'Old short desc' ],
				[ 'shortdescription' => 'New short desc' ],
				'Updated short description',
			],
			'owners changes' => [
				[ 'owners' => "* Community Tech" ],
				[ 'owners' => "* Community Tech\n* Editing" ],
				'Updated owners',
			],
			'volunteers changes' => [
				[ 'volunteers' => "* [[User:Volunteer1]]" ],
				[ 'volunteers' => "* [[User:Volunteer1]]\n* [[User:Volunteer2]]" ],
				'Updated volunteers',
			],
			'with translations and multiple changes' => [
				[
					'status' => 'under-review',
					'description' => '<translate>Old description.</translate>',
					'shortdescription' => '<translate>Unchanged short desc</translate>',
					'owners' => '<translate>* Community Tech</translate>'
				],
				[
					'status' => 'prioritized',
					'description' => '<translate>New description.</translate>',
					'shortdescription' => '<translate>Unchanged short desc</translate>',
					'owners' => '<translate>* Community Tech\n* Editing</translate>'
				],
				'Updated description; Changed status from "Under review" to "Prioritized"; Updated owners',
			],
		];
	}
}
