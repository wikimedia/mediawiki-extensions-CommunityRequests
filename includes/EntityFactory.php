<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Page\PageIdentity;
use MediaWiki\User\UserFactory;

class EntityFactory {
	public function __construct(
		private readonly WishlistConfig $config,
		private readonly UserFactory $userFactory,
	) {
	}

	/**
	 * Create an AbstractWishlistEntity from the array stored to the ParserOutput's
	 * extension data. This array originally comes from the parser function renderer.
	 *
	 * @param array $data An associative array of parameters
	 * @param PageIdentity $baseTitle The base title of the wish or focus area
	 * @return AbstractWishlistEntity
	 */
	public function createFromParserData( array $data, PageIdentity $baseTitle ): AbstractWishlistEntity {
		return match ( $data[AbstractWishlistEntity::PARAM_ENTITY_TYPE] ?? '' ) {
			'wish' => Wish::newFromWikitextParams(
				$baseTitle,
				$data['lang'],
				$data,
				$this->config,
				$this->userFactory->newFromName( $data[Wish::PARAM_PROPOSER] ?? '' ),
			),
			'focus-area' => FocusArea::newFromWikitextParams(
				$baseTitle,
				$data['lang'],
				$data,
				$this->config
			)
		};
	}
}
