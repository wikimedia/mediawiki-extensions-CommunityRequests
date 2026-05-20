<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\Wish\SpecialWishlistIntake;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Tests\Specials\SpecialPageTestBase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractWishlistSpecialPage
 */
class SpecialWishlistIntakeCaptchaTest extends SpecialPageTestBase {
	use WishlistTestTrait;

	protected function setUp(): void {
		parent::setUp();
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ) ) {
			$this->markTestSkipped( 'ConfirmEdit is not loaded' );
		}
		\OOUI\Theme::setSingleton( new \OOUI\BlankTheme() );
	}

	protected function tearDown(): void {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ) ) {
			$this->getServiceContainer()->get( 'ConfirmEditCaptchaFactory' )->unsetGlobalInstancesForTests();
		}
		parent::tearDown();
	}

	protected function getStore(): AbstractWishlistStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	protected function newSpecialPage(): SpecialWishlistIntake {
		$services = $this->getServiceContainer();
		return new SpecialWishlistIntake(
			$services->get( 'CommunityRequests.WishlistConfig' ),
			$services->get( 'CommunityRequests.WishStore' ),
			$services->get( 'CommunityRequests.FocusAreaStore' ),
			$services->get( 'TitleParser' ),
			$services->getUserFactory(),
			$services->get( 'CommunityRequests.Logger' ),
			$services->get( 'ConfirmEditCaptchaFactory' ),
		);
	}

	private function configureHCaptcha( array $overrides = [] ): void {
		$this->overrideConfigValues( $overrides + [
			'CaptchaClass' => 'HCaptcha',
			'CaptchaTriggers' => [
				'create' => true,
				'edit' => true,
				'addurl' => false,
				'createaccount' => false,
				'badlogin' => false,
				'badloginperuser' => false,
			],
			'CaptchaTriggersOnNamespace' => [],
			'HCaptchaSiteKey' => 'test-site-key',
			'HCaptchaSecretKey' => 'test-secret-key',
		] );
		$this->getServiceContainer()->get( 'ConfirmEditCaptchaFactory' )->unsetGlobalInstancesForTests();
	}

	public function testCaptchaWidgetRenderedWhenHCaptchaTriggersForNamespace(): void {
		$this->configureHCaptcha();

		[ $html ] = $this->executeSpecialPage( '', null, null, $this->getTestUser()->getAuthority() );

		$this->assertStringContainsString( 'h-captcha', $html );
		$this->assertStringContainsString( 'data-sitekey', $html );
	}

	public function testCaptchaWidgetNotRenderedWhenUserCanSkipCaptcha(): void {
		$this->configureHCaptcha();
		$this->setGroupPermissions( [ 'user' => [ 'skipcaptcha' => true ] ] );

		[ $html ] = $this->executeSpecialPage( '', null, null, $this->getTestUser()->getAuthority() );

		$this->assertStringNotContainsString( 'h-captcha', $html );
	}

	public function testCaptchaWidgetNotRenderedWhenTriggerDisabledForNamespace(): void {
		$this->configureHCaptcha( [
			'CaptchaTriggersOnNamespace' => [
				// EDIT fires for creation too, so both must be off to suppress the widget.
				NS_MAIN => [ 'create' => false, 'edit' => false ],
			],
		] );

		[ $html ] = $this->executeSpecialPage( '', null, null, $this->getTestUser()->getAuthority() );

		$this->assertStringNotContainsString( 'h-captcha', $html );
	}

	public function testCaptchaWidgetRenderedWhenActiveCaptchaIsNotHCaptcha(): void {
		$this->configureHCaptcha( [ 'CaptchaClass' => 'SimpleCaptcha' ] );

		[ $html ] = $this->executeSpecialPage( '', null, null, $this->getTestUser()->getAuthority() );

		$this->assertStringContainsString( 'class="ext-communityrequests-captcha"', $html );
		$this->assertStringNotContainsString( 'h-captcha', $html );
	}
}
