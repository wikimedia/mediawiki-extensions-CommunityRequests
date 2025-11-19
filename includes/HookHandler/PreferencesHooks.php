<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\User;

/**
 * Hook handlers for user preferences.
 */
class PreferencesHooks implements GetPreferencesHook {

	public const PREF_MACHINETRANSLATION = 'usemachinetranslation';

	private bool $translateInstalled;

	public function __construct(
		protected readonly WishlistConfig $config,
		?ExtensionRegistry $extensionRegistry = null
	) {
		$extensionRegistry ??= ExtensionRegistry::getInstance();
		$this->translateInstalled = $extensionRegistry->isLoaded( 'Translate' );
	}

	/**
	 * Add preference for machine translations.
	 *
	 * @param User $user
	 * @param array &$preferences
	 */
	public function onGetPreferences( $user, &$preferences ) {
		if ( !$this->config->isEnabled() ) {
			return;
		}
		$preferences[self::PREF_MACHINETRANSLATION] = [
			'type' => 'toggle',
			'label-message' => [
				'communityrequests-wishlist-machine-translation',
				( $this->translateInstalled ? 'Special:MyLanguage/' : '' ) . $this->config->getHomepage(),
			],
			'section' => 'personal/i18n',
		];
	}
}
