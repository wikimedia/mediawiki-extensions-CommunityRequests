<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\Title;
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
	 * Expects AbstractWishlistEntity::PARAM_ENTITY_TYPE and ::PARAM_LANG to be set in $data.
	 * Use ::newFromPageIdentity() if you don't have these values already.
	 *
	 * @param array $data An associative array of parameters
	 * @param PageIdentity $baseTitle The base title of the wish or focus area
	 * @return AbstractWishlistEntity
	 */
	public function createFromParserData( array $data, PageIdentity $baseTitle ): AbstractWishlistEntity {
		return match ( $data[AbstractWishlistEntity::PARAM_ENTITY_TYPE] ?? '' ) {
			'wish' => Wish::newFromWikitextParams(
				$baseTitle,
				$data[AbstractWishlistEntity::PARAM_LANG],
				$data,
				$this->config,
				$this->userFactory->newFromName( $data[Wish::PARAM_PROPOSER] ?? '' ),
			),
			'focus-area' => FocusArea::newFromWikitextParams(
				$baseTitle,
				$data[AbstractWishlistEntity::PARAM_LANG],
				$data,
				$this->config
			)
		};
	}

	/**
	 * Create an AbstractWishlistEntity from the given data (may come parser or ::toArray() methods)
	 *
	 * @param array $data An associative array of parameters
	 * @param PageIdentity $page The page identity of the wish or focus area
	 * @return AbstractWishlistEntity
	 */
	public function newFromPageIdentity( array $data, PageIdentity $page ): AbstractWishlistEntity {
		$data[AbstractWishlistEntity::PARAM_ENTITY_TYPE] = $this->config->isWishPage( $page ) ? 'wish' : 'focus-area';
		$data[AbstractWishlistEntity::PARAM_LANG] = Title::newFromPageIdentity( $page )->getPageLanguage()->getCode();
		return $this->createFromParserData( $data, $page );
	}
}
