<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\SpecialWishlist;
use MediaWiki\Extension\CommunityRequests\Tests\Unit\MockWishlistConfigTrait;
use MediaWikiIntegrationTestCase;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\SpecialWishlist
 */
class SpecialWishlistTest extends MediaWikiIntegrationTestCase {

	use MockWishlistConfigTrait;

	/**
	 * @dataProvider provideTestGetRedirect
	 */
	public function testGetRedirect( string $subpage, string $expected ): void {
		$specialWishlist = new SpecialWishlist( $this->getConfig() );
		$this->assertSame( $expected, $specialWishlist->getRedirect( $subpage )->getPrefixedText() );
	}

	public static function provideTestGetRedirect(): array {
		return [
			'no subpage' => [
				'',
				'Community Wishlist',
			],
			'invalid subpage' => [
				'InvalidSubpage',
				'Community Wishlist',
			],
			'valid wish' => [
				'W123',
				'Community Wishlist/W123',
			],
			'valid focus area' => [
				'FA45',
				'Community Wishlist/FA45',
			],
		];
	}
}
