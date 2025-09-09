<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\IdGenerator;

use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use RuntimeException;

/**
 * Generates a new unique numeric id for the provided type.
 * Ids are only unique per type.
 */
interface IdGenerator {

	public const TYPE_WISH = AbstractWishlistStore::ENTITY_TYPE_WISH;
	public const TYPE_FOCUS_AREA = AbstractWishlistStore::ENTITY_TYPE_FOCUS_AREA;

	/**
	 * @param int $type One of the IdGenerator::TYPE_ constants
	 * @return int
	 * @throws RuntimeException
	 */
	public function getNewId( int $type ): int;
}
