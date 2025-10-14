<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader as RL;

/**
 * Provides the static ::addDynamicMessages method to add in messages defined in configuration.
 * For use by the 'ext.communityRequests.intake' and 'ext.communityRequests.wish-index' modules.
 */
class WishlistMessageLoader {

	public static function addDynamicMessages( array $moduleConfig ): RL\Module {
		$messages = [];
		$config = MediaWikiServices::getInstance()->getMainConfig();
		foreach ( $config->get( 'CommunityRequestsWishTypes' ) as $type ) {
			$messages[] = $type['label'] . '-label';
			$messages[] = $type['label'] . '-description';
		}
		foreach ( $config->get( 'CommunityRequestsTags' ) as $tagGroup ) {
			foreach ( $tagGroup as $tag => $tagConfig ) {
				$messages[] = $tagConfig['label'] ?? "communityrequests-tag-$tag";
			}
		}

		// Build status messages for focus areas and wishes. Half won't be needed, but we use the same module for both.
		foreach ( [ 'wish', 'focus-area' ] as $entityType ) {
			foreach ( $config->get( 'CommunityRequestsStatuses' ) as $status => $statusInfo ) {
				$messages[] = 'communityrequests-status-' . $entityType . '-' . $status;
			}
		}

		$moduleConfig['messages'] = array_merge( $moduleConfig['messages'], $messages );
		$class = $moduleConfig['class'] ?? RL\FileModule::class;
		return new $class( $moduleConfig );
	}
}
