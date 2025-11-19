<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsExpensiveHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Psr\Log\LoggerInterface;
use Wikimedia\Message\MessageSpecifier;

class PermissionHooks implements GetUserPermissionsErrorsExpensiveHook {

	public function __construct(
		protected readonly WishlistConfig $config,
		protected readonly PermissionManager $permissionManager,
		protected readonly SpecialPageFactory $specialPageFactory,
		protected readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Whether the user is allowed to manually edit wish and focus area pages.
	 * This is set to true when the user is editing a wish or focus area using the special pages,
	 * and in some tests.
	 */
	public static bool $allowManualEditing = false;

	/**
	 * Prevent manual editing of wish and focus area pages unless the user has the 'manually-edit-wishlist' right.
	 *
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param array|string|MessageSpecifier &$result
	 * @return bool
	 */
	public function onGetUserPermissionsErrorsExpensive( $title, $user, $action, &$result ): bool {
		if ( !$this->config->isEnabled() || !$this->isEntityPageOrEditPage( $title ) ) {
			return true;
		}
		if ( $action !== 'edit' ) {
			return true;
		}

		// If $allowManualEditing is set, it means the user is editing an entity or vote using a form or API.
		if ( self::$allowManualEditing ) {
			return true;
		}

		$userHasRight = $this->permissionManager->userHasRight( $user, 'manually-edit-wishlist' );
		if ( !$userHasRight || !$title->exists() ) {
			$result = [];

			// Conditionally show messages based on rights or page existence (T403505).

			if ( !$userHasRight || !$title->exists() ) {
				if ( $this->config->isVotesPage( $title ) ) {
					$canonicalEntityPage = $this->config->getEntityPageRefFromVotesPage( $title );
					if ( !$canonicalEntityPage ) {
						// This should not happen, but bail out gracefully if it does.
						$this->logger->error(
							__METHOD__ . ': Could not determine canonical entity page for votes page {0}',
							[ $title->toPageIdentity()->__toString() ]
						);
						return true;
					}
					// Message instructing users to use Vote form on the entity page.
					$result[] = [
						'communityrequests-cant-manually-edit-votes',
						Title::newFromPageReference( $canonicalEntityPage )
					];
				} else {
					// Message instructing users to use the Special page form.
					$result[] = [
						'communityrequests-cant-manually-edit',
						$this->specialPageFactory->getPage(
							$this->config->isWishPage( $title ) ? 'WishlistIntake' : 'EditFocusArea'
						)->getPageTitle( $this->config->getEntityWikitextVal( $title ) ),
					];
				}
			}
			if ( !$userHasRight ) {
				// Standard message listing the user groups that are allowed to manually edit.
				$result[] = $this->permissionManager->newFatalPermissionDeniedStatus(
					'manually-edit-wishlist',
					RequestContext::getMain()
				)->getMessages()[0];
			}

			return false;
		}

		return true;
	}

	private function isEntityPageOrEditPage( PageIdentity $identity ): bool {
		return $this->config->isWishOrFocusAreaPage( $identity ) ||
			$this->config->isVotesPage( $identity ) || (
				$identity->getNamespace() === NS_SPECIAL && (
					str_starts_with( $identity->getDBkey(), 'WishlistIntake' ) ||
					str_starts_with( $identity->getDBkey(), 'EditFocusArea' )
				)
			);
	}
}
