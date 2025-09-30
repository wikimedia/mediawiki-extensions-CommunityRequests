<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Vote;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;

/**
 * A value object representing a vote on a wishlist entity.
 */
class Vote {

	// Constants used for the parser function and API parameters.
	public const PARAM_USERNAME = 'username';
	public const PARAM_COMMENT = 'comment';
	public const PARAM_TIMESTAMP = 'timestamp';
	public const PARAM_ENTITY = 'entity';
	public const PARAM_ACTION = 'voteaction';
	public const PARAM_BASE_REV_ID = 'baserevid';
	// Parser function parameter names.
	public const PARAMS = [
		self::PARAM_USERNAME,
		self::PARAM_COMMENT,
		self::PARAM_TIMESTAMP,
	];

	public function __construct(
		protected AbstractWishlistEntity $entity,
		protected UserIdentity $user,
		protected string $comment = '',
		protected ?string $timestamp = null
	) {
		$this->timestamp = $timestamp ?? MWTimestamp::now( TS_ISO_8601 );
	}

	/**
	 * Get the wishlist entity this vote is for.
	 *
	 * @return AbstractWishlistEntity
	 */
	public function getEntity(): AbstractWishlistEntity {
		return $this->entity;
	}

	/**
	 * Get the user who cast this vote.
	 *
	 * @return UserIdentity
	 */
	public function getUser(): UserIdentity {
		return $this->user;
	}

	/**
	 * Get the comment associated with this vote.
	 *
	 * @return string
	 */
	public function getComment(): string {
		return $this->comment;
	}

	/**
	 * Get the timestamp when this vote was cast.
	 *
	 * @return string Timestamp in ISO 8601 format
	 */
	public function getTimestamp(): string {
		return $this->timestamp;
	}

	/**
	 * Convert the vote to an associative array, useful for JSON serialization.
	 *
	 * @param WishlistConfig $config
	 * @return array
	 */
	public function toArray( WishlistConfig $config ): array {
		return [
			self::PARAM_ENTITY => $config->getEntityWikitextVal( $this->entity->getPage() ),
			self::PARAM_USERNAME => $this->user->getName(),
			self::PARAM_COMMENT => $this->comment,
			self::PARAM_TIMESTAMP => $this->timestamp,
		];
	}

	/**
	 * Convert the vote to wikitext, ready for storage by ApiWishlistVote.
	 *
	 * @return WikitextContent
	 */
	public function toWikitext(): WikitextContent {
		$wikitext = '{{#CommunityRequests:vote|'
			. self::PARAM_USERNAME . '=' . $this->user->getName() . '|'
			. self::PARAM_COMMENT . '=' . $this->comment . '|'
			. self::PARAM_TIMESTAMP . '=' . $this->timestamp
			. "}}\n";
		return new WikitextContent( $wikitext );
	}
}
