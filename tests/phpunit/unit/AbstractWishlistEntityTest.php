<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
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
			WishlistConfig::WISH_TEMPLATE => [
				'page' => 'Template:Wish',
				'params' => [
					'status' => 'status',
					'type' => 'type',
					'title' => 'title',
					'focusarea' => 'focusarea',
					'description' => 'description',
					'audience' => 'audience',
					'projects' => 'projects',
					'otherproject' => 'otherproject',
					'phabtasks' => 'tasks',
					'proposer' => 'proposer',
					'created' => 'created',
					'baselang' => 'baselang',
				]
			],
			WishlistConfig::WISH_TYPES => [
				'bug' => [ 'id' => 1 ],
				'change' => [ 'id' => 2 ],
			],
			WishlistConfig::FOCUS_AREA_CATEGORY => '',
			WishlistConfig::FOCUS_AREA_PAGE_PREFIX => '',
			WishlistConfig::FOCUS_AREA_INDEX_PAGE => '',
			WishlistConfig::FOCUS_AREA_TEMPLATE => [
				'page' => 'Template:FocusArea',
				'params' => [
					'title' => 'title',
					'description' => 'description',
					'shortdescription' => 'short_description',
					'status' => 'status',
					'owners' => 'owners',
					'volunteers' => 'volunteers',
					'created' => 'created',
					'updated' => 'updated',
					'baselang' => 'baselang',
				]
			],
			WishlistConfig::PROJECTS => [
				'wikipedia' => [ 'id' => 0 ],
				'wikidata' => [ 'id' => 1 ],
				'commons' => [ 'id' => 2 ],
				'wikisource' => [ 'id' => 3 ],
				'wiktionary' => [ 'id' => 4 ],
				'wikivoyage' => [ 'id' => 5 ],
				'wikiquote' => [ 'id' => 6 ],
				'wikiversity' => [ 'id' => 7 ],
				'wikifunctions' => [ 'id' => 8 ],
				'wikispecies' => [ 'id' => 9 ],
				'wikinews' => [ 'id' => 10 ],
				'metawiki' => [ 'id' => 11 ],
				'wmcs' => [ 'id' => 12 ],
			],
			WishlistConfig::STATUSES => [
				'submitted' => [ 'id' => 1 ],
				'archived' => [ 'id' => 6 ],
			],
		] );
		$this->config = new WishlistConfig(
			$serviceOptions,
			$this->newServiceInstance( TitleParser::class, [ 'localInterwikis' => [] ] ),
			$this->newServiceInstance( TitleFormatter::class, [] )
		);
	}
}
