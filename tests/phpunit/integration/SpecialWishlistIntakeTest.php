<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\Wish\SpecialWishlistIntake;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Title\Title;
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
		$wish = $this->insertTestWish();
		$this->resetCount();
		$this->executeSpecialPage( $wish->getPage()->getId(), null, 'en', $wish->getProposer() );
		$this->expectNotToPerformAssertions();
	}

	public function testEditExistingHasTranslateTags(): void {
		$wish = $this->insertTestWish(
			'Community Wishlist/W1',
			'en',
			[
				Wish::PARAM_TITLE => '<translate>Test Wish</translate>',
				Wish::PARAM_DESCRIPTION => '<translate>This is a [[test]] {{wish}}.</translate>',
				Wish::PARAM_AUDIENCE => '<translate>Example audience</translate>',
			]
		);
		$pageId = $wish->getPage()->getId();

		$sp = $this->newSpecialPage();
		$sp->loadExistingEntity( $pageId, Title::newFromPageIdentity( $wish->getPage() ) );
		$vars = $sp->getOutput()->getJsConfigVars();
		$this->assertSame( $vars['intakeId'], $pageId );
		$this->assertSame( '<translate><!--T:1--> Test Wish</translate>', $vars['intakeData'][Wish::PARAM_TITLE] );
		$this->assertSame(
			'<translate><!--T:2--> This is a [[test]] {{wish}}.</translate>',
			$vars['intakeData'][Wish::PARAM_DESCRIPTION]
		);
		$this->assertSame(
			'<translate><!--T:3--> Example audience</translate>',
			$vars['intakeData'][Wish::PARAM_AUDIENCE]
		);
	}
}
