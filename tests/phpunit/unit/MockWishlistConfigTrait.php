<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Language\LanguageNameUtils;
use MediaWiki\MainConfigNames;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleParser;

/**
 * Trait for mocking WishlistConfig in unit tests.
 */
trait MockWishlistConfigTrait {

	use MockServiceDependenciesTrait;

	/**
	 * Get a mock WishlistConfig with default or overridden options.
	 *
	 * @param array $serviceOptions Options to override the defaults.
	 * @return WishlistConfig
	 */
	protected function getConfig( array $serviceOptions = [] ): WishlistConfig {
		$serviceOptions = new ServiceOptions( WishlistConfig::CONSTRUCTOR_OPTIONS, [
			WishlistConfig::ENABLED => true,
			WishlistConfig::HOMEPAGE => 'Community Wishlist',
			WishlistConfig::WISH_CATEGORY => 'Category:Community Wishlist',
			WishlistConfig::WISH_PAGE_PREFIX => 'Community Wishlist/W',
			WishlistConfig::WISH_INDEX_PAGE => 'Community Wishlist/Wishes',
			WishlistConfig::WISH_TYPES => [
				'feature' => [ 'id' => 0, 'label' => 'communityrequests-wishtype-feature' ],
				'bug' => [ 'id' => 1, 'label' => 'communityrequests-wishtype-bug' ],
				'change' => [ 'id' => 2, 'label' => 'communityrequests-wishtype-change' ],
				'unknown' => [ 'id' => 3, 'label' => 'communityrequests-wishtype-unknown' ],
			],
			WishlistConfig::FOCUS_AREA_CATEGORY => 'Category:Community Wishlist/Focus areas',
			WishlistConfig::FOCUS_AREA_PAGE_PREFIX => 'Community Wishlist/FA',
			WishlistConfig::FOCUS_AREA_INDEX_PAGE => 'Community Wishlist/Focus areas',
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
				'under-review' => [ 'id' => 0, 'default' => true, 'voting' => false ],
				'community-opportunity' => [ 'id' => 3 ],
				'declined' => [ 'id' => 6, 'voting' => false ],
				'in-progress' => [ 'id' => 7 ],
			],
			WishlistConfig::VOTES_PAGE_SUFFIX => '/Votes',
			WishlistConfig::WISH_VOTING_ENABLED => true,
			WishlistConfig::FOCUS_AREA_VOTING_ENABLED => true,
			MainConfigNames::LanguageCode => 'en',
			...$serviceOptions,
		] );
		return new WishlistConfig(
			$serviceOptions,
			$this->newServiceInstance( TitleParser::class, [ 'localInterwikis' => [] ] ),
			$this->newServiceInstance( TitleFormatter::class, [] ),
			$this->newServiceInstance( LanguageNameUtils::class, [
				'options' => new ServiceOptions( LanguageNameUtils::CONSTRUCTOR_OPTIONS, [
					MainConfigNames::ExtraLanguageNames => [],
					MainConfigNames::UsePigLatinVariant => false,
					MainConfigNames::UseXssLanguage => false,
				] )
			] ),
		);
	}

	/**
	 * Get a mock SpecialPageFactory with relevant SpecialPages mocked.
	 *
	 * @return SpecialPageFactory
	 */
	protected function getSpecialPageFactory(): SpecialPageFactory {
		$specialWishlistIntake = $this->createNoOpMock( SpecialPage::class, [ 'getPageTitle' ] );
		$specialWishlistIntake->expects( $this->any() )
			->method( 'getPageTitle' )
			->willReturn( $this->makeMockTitle( 'WishlistIntake', [ 'namespace' => NS_SPECIAL ] ) );
		$specialEditFocusArea = $this->createNoOpMock( SpecialPage::class, [ 'getPageTitle' ] );
		$specialEditFocusArea->expects( $this->any() )
			->method( 'getPageTitle' )
			->willReturn( $this->makeMockTitle( 'EditFocusArea', [ 'namespace' => NS_SPECIAL ] ) );
		$specialPageFactory = $this->createNoOpMock( SpecialPageFactory::class, [ 'getPage' ] );
		$specialPageFactory->expects( $this->atMost( 1 ) )
			->method( 'getPage' )
			->willReturnMap( [
				[ 'WishlistIntake', $specialWishlistIntake ],
				[ 'EditFocusArea', $specialEditFocusArea ],
			] );
		return $specialPageFactory;
	}
}
