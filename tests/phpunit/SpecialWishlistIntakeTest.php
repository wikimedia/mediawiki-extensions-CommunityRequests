<?php

use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Extension\CommunityRequests\Wish\SpecialWishlistIntake;

/**
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\Wish\SpecialWishlistIntake
 * @group Database
 */
class SpecialWishlistIntakeTest extends SpecialPageTestBase {
	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'WishlistIntake' );
	}

	/**
	 * @covers ::addResourceLoaderMessages
	 */
	public function testGetMessages(): void {
		$this->overrideConfigValues( [
			'CommunityRequestsWishTypes' => [
				[
					'id' => 0,
					'label' => 'communityrequests-wishtype-feature'
				], [
					'id' => 1,
					'label' => 'communityrequests-wishtype-bug'
				]
			],
			'CommunityRequestsProjects' => [
				[
					'id' => 0,
					'label' => 'project-localized-name-group-wikipedia'
				], [
					'id' => 1,
					'label' => 'communityrequests-project-wikimedia'
				]
			],
			'CommunityRequestsStatuses' => [
				'draft' => [
					'id' => 0,
					'label' => 'communityrequests-status-draft'
				],
				'submitted' => [
					'id' => 1,
					'label' => 'communityrequests-status-accepted'
				]
			],
		] );

		$actual = SpecialWishlistIntake::addResourceLoaderMessages( [ 'messages' => [] ] );
		$this->assertArrayEquals( [
			'communityrequests-project-wikimedia',
			'communityrequests-status-accepted',
			'communityrequests-status-draft',
			'communityrequests-wishtype-bug-description',
			'communityrequests-wishtype-bug-label',
			'communityrequests-wishtype-feature-description',
			'communityrequests-wishtype-feature-label',
			'project-localized-name-group-wikipedia',
		], $actual->getMessages(), true, true );
	}

	/**
	 * @covers ::execute
	 */
	public function testLoggedOut(): void {
		$this->expectException( UserNotLoggedIn::class );
		$this->executeSpecialPage();
	}

	/**
	 * @covers ::execute
	 */
	public function testNotFound(): void {
		[ $html ] = $this->executeSpecialPage( '12345', null, null, $this->getTestUser()->getAuthority() );
		$this->assertStringContainsString(
			'communityrequests-wish-not-found',
			$html,
			'Should show error page when wish is not found'
		);
	}
}
