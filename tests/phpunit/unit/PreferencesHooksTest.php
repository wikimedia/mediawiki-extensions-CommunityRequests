<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Extension\CommunityRequests\HookHandler\PreferencesHooks;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\HookHandler\PreferencesHooks
 */
class PreferencesHooksTest extends MediaWikiUnitTestCase {

	use MockWishlistConfigTrait;

	/**
	 * @dataProvider provideOnGetPreferences
	 */
	public function testOnGetPreferences( bool $isEnabled, bool $translateLoaded, array $expectedPrefs ): void {
		$extensionRegistry = $this->createNoOpMock( ExtensionRegistry::class, [ 'isLoaded' ] );
		$extensionRegistry->expects( $this->once() )
			->method( 'isLoaded' )
			->with( 'Translate' )
			->willReturn( $translateLoaded );
		$handler = new PreferencesHooks(
			$this->getConfig( [ WishlistConfig::ENABLED => $isEnabled ] ),
			$extensionRegistry
		);
		$preferences = [];
		$handler->onGetPreferences( $this->createNoOpMock( User::class ), $preferences );
		$this->assertSame( $expectedPrefs, $preferences );
	}

	public static function provideOnGetPreferences(): array {
		return [
			'disabled' => [ false, false, [] ],
			'enabled without Translate' => [
				true,
				false,
				[
					PreferencesHooks::PREF_MACHINETRANSLATION => [
						'type' => 'toggle',
						'label-message' => [
							'communityrequests-wishlist-machine-translation',
							'Community Wishlist',
						],
						'section' => 'personal/i18n',
					],
				],
			],
			'enabled with Translate' => [
				true,
				true,
				[
					PreferencesHooks::PREF_MACHINETRANSLATION => [
						'type' => 'toggle',
						'label-message' => [
							'communityrequests-wishlist-machine-translation',
							'Special:MyLanguage/Community Wishlist',
						],
						'section' => 'personal/i18n',
					],
				],
			],
		];
	}
}
