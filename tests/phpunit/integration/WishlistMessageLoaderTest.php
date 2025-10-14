<?php

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\WishlistMessageLoader;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CommunityRequests\WishlistMessageLoader
 */
class WishlistMessageLoaderTest extends MediaWikiIntegrationTestCase {

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

		$actual = WishlistMessageLoader::addDynamicMessages( [ 'messages' => [] ] );
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
}
