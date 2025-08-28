<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Language\LanguageNameUtils;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleParser;
use MediaWikiUnitTestCase;

/**
 * @group CommunityRequests
 * @coversNothing
 */
class AbstractWishlistEntityTest extends MediaWikiUnitTestCase {

	use MockServiceDependenciesTrait;

	protected WishlistConfig $config;

	protected function setUp(): void {
		parent::setUp();
		$serviceOptions = new ServiceOptions( WishlistConfig::CONSTRUCTOR_OPTIONS, [
			WishlistConfig::ENABLED => true,
			WishlistConfig::HOMEPAGE => '',
			WishlistConfig::WISH_CATEGORY => '',
			WishlistConfig::WISH_PAGE_PREFIX => '',
			WishlistConfig::WISH_INDEX_PAGE => '',
			WishlistConfig::WISH_TYPES => [
				'bug' => [ 'id' => 1 ],
				'change' => [ 'id' => 2 ],
			],
			WishlistConfig::FOCUS_AREA_CATEGORY => '',
			WishlistConfig::FOCUS_AREA_PAGE_PREFIX => 'Community Wishlist/Focus Areas/',
			WishlistConfig::FOCUS_AREA_INDEX_PAGE => '',
			WishlistConfig::TAGS => [
				'navigation' => [
					'admins' => [ 'id' => 0 ],
					'botsgadgets' => [ 'id' => 1 ],
					'categories' => [ 'id' => 2 ],
					'citations' => [ 'id' => 3 ],
					'editing' => [ 'id' => 4 ],
					'ios' => [ 'id' => 5 ],
					'android' => [ 'id' => 6 ],
					'mobileweb' => [ 'id' => 7 ],
					'multimedia' => [ 'id' => 8 ],
					'newcomers' => [ 'id' => 9 ],
					'notifications' => [ 'id' => 10 ],
					'patrolling' => [ 'id' => 11 ],
					'reading' => [ 'id' => 12 ],
					'search' => [ 'id' => 13 ],
					'talkpages' => [ 'id' => 14 ],
					'templates' => [ 'id' => 15 ],
					'translation' => [ 'id' => 16 ],
					'changelists' => [ 'id' => 17 ],
					'wikidata' => [ 'id' => 18 ],
					'wikisource' => [ 'id' => 19 ],
					'wiktionary' => [ 'id' => 20 ],
				]
			],
			WishlistConfig::STATUSES => [
				'submitted' => [ 'id' => 1 ],
				'archived' => [ 'id' => 6 ],
			],
			WishlistConfig::VOTES_PAGE_SUFFIX => '',
			WishlistConfig::WISH_VOTING_ENABLED => true,
			WishlistConfig::FOCUS_AREA_VOTING_ENABLED => true,
			MainConfigNames::LanguageCode => 'en',
		] );
		$this->config = new WishlistConfig(
			$serviceOptions,
			$this->newServiceInstance( TitleParser::class, [ 'localInterwikis' => [] ] ),
			$this->newServiceInstance( TitleFormatter::class, [] ),
			$this->newServiceInstance( LanguageNameUtils::class, [] ),
		);
	}
}
