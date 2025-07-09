<?php

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Page\PageIdentity;
use MediaWiki\User\UserFactory;

class EntityFactory {
	public function __construct(
		private WishlistConfig $config,
		private UserFactory $userFactory,
	) {
	}

	/**
	 * Create an AbstractWishlistEntity from the array stored to the
	 * ParserOutput's extension data. This array originally comes from the
	 * template renderer.
	 *
	 * @param array $data An associative array of parameters
	 * @param PageIdentity $baseTitle The base title of the wish or focus area
	 * @param string $lang The language code
	 * @return AbstractWishlistEntity
	 */
	public function createFromParserData(
		array $data,
		PageIdentity $baseTitle,
		string $lang
	) {
		return match ( $data['entityType'] ?? '' ) {
			'wish' => Wish::newFromWikitextParams(
				$baseTitle,
				$lang,
				$data,
				$this->config,
				$this->userFactory->newFromName( $data[ Wish::TAG_ATTR_PROPOSER ] ?? '' ),
			),
			'focus-area' => FocusArea::newFromWikitextParams(
				$baseTitle,
				$lang,
				$data,
				$this->config
			)
		};
	}
}
