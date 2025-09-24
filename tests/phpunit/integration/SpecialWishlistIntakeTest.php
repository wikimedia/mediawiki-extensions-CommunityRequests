<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\Wish\SpecialWishlistIntake;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\User\User;
use SpecialPageTestBase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\Wish\SpecialWishlistIntake
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractWishlistSpecialPage
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractWishlistStore
 */
class SpecialWishlistIntakeTest extends SpecialPageTestBase {
	use WishlistTestTrait;

	protected function getStore(): AbstractWishlistStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage(): SpecialWishlistIntake {
		$services = $this->getServiceContainer();
		return new SpecialWishlistIntake(
			$services->get( 'CommunityRequests.WishlistConfig' ),
			$services->get( 'CommunityRequests.WishStore' ),
			$services->get( 'CommunityRequests.FocusAreaStore' ),
			$services->get( 'TitleParser' ),
			$services->getUserFactory(),
			$services->get( 'CommunityRequests.Logger' ),
		);
	}

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
			'CommunityRequestsTags' => [
				'navigation' => [
					'admins' => [
						'id' => 0,
						'category' => 'Category:Community Wishlist/Wishes/Admins and stewards',
					],
					'botsgadgets' => [
						'id' => 1,
						'category' => 'Category:Community Wishlist/Wishes/Bots and gadgets',
						'label' => 'communityrequests-tag-bots-gadgets',
					]
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
			'communityrequests-status-accepted',
			'communityrequests-status-draft',
			'communityrequests-tag-admins',
			'communityrequests-tag-bots-gadgets',
			'communityrequests-wishtype-bug-description',
			'communityrequests-wishtype-bug-label',
			'communityrequests-wishtype-feature-description',
			'communityrequests-wishtype-feature-label',
		], $actual->getMessages(), true, true );
	}

	public function testLoggedOut(): void {
		$this->expectException( UserNotLoggedIn::class );
		$this->executeSpecialPage();
	}

	public function testNotFound(): void {
		[ $html ] = $this->executeSpecialPage( '12345', null, null, $this->getTestUser()->getAuthority() );
		$this->assertStringContainsString(
			'communityrequests-wish-not-found',
			$html,
			'Should show error page when wish is not found'
		);
	}

	public function testEditExistingThrowsNoException(): void {
		$user = User::createNew( 'TestUser' );

		$wikitext = <<<END
{{#CommunityRequests: wish
|title = Test Wish
|status = prioritized
|type = change
|audience = Example audience
|tags = multimedia,wikisource
|phabtasks = T123,T456
|created = 2023-10-01T12:00:00Z
|proposer = TestUser
|baselang = en
|description = This is a [[test]] {{wish}}.
}}
END;
		$this->insertPage( 'Community Wishlist/W1', $wikitext );
		$this->executeSpecialPage( 'W1', null, 'en', $user );
		$this->expectNotToPerformAssertions();
	}

	public function testEditExistingHasTranslateTags(): void {
		User::createNew( 'TestUser' );
		$wikitext = <<<END
{{#CommunityRequests: wish
|title = <translate>Test Wish</translate>
|status = prioritized
|type = change
|audience = <translate>Example audience</translate>
|tags = multimedia,wikisource
|phabtasks = T123,T456
|created = 2023-10-01T12:00:00Z
|proposer = TestUser
|baselang = en
|description = <translate>This is a [[test]] {{wish}}.</translate>
}}
END;
		$ret = $this->insertPage( 'Community Wishlist/W1', $wikitext );
		$this->markForTranslation( $ret['title'] );

		$sp = $this->newSpecialPage();
		$sp->loadExistingEntity( $ret['id'], $ret['title'] );
		$vars = $sp->getOutput()->getJsConfigVars();
		$this->assertSame( $vars['intakeId'], $ret['id'] );
		$this->assertSame( '<translate><!--T:1--> Test Wish</translate>', $vars['intakeData'][Wish::PARAM_TITLE] );
		$this->assertSame(
			'<translate><!--T:2--> Example audience</translate>',
			$vars['intakeData'][Wish::PARAM_AUDIENCE]
		);
		$this->assertSame(
			'<translate><!--T:3--> This is a [[test]] {{wish}}.</translate>',
			$vars['intakeData'][Wish::PARAM_DESCRIPTION]
		);
	}
}
