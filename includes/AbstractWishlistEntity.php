<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Page\PageIdentity;

/**
 * Shared properties and methods for Wish and FocusArea.
 */
abstract class AbstractWishlistEntity {

	// Constants used for parsing and constructing the parser function invocation.
	public const PARAM_STATUS = 'status';
	public const PARAM_TITLE = 'title';
	public const PARAM_DESCRIPTION = 'description';
	public const PARAM_CREATED = 'created';
	public const PARAM_BASE_LANG = 'baselang';
	public const PARAM_VOTE_COUNT = 'votecount';

	protected string $baseLang;
	protected string $title;
	protected int $status;
	protected ?string $description;
	protected ?int $voteCount;
	protected ?string $created;
	protected ?string $updated;

	/**
	 * @param PageIdentity $page The page representing the wish or focus area.
	 * @param string $lang The language (or translated language) of the wish or focus area.
	 * @param array $fields The fields, including:
	 *   - 'title' (string): The title of the wish or focus area.
	 *   - 'status' (int): The status ID of the wish or focus area.
	 *   - 'description' (?string): The description of the wish or focus area.
	 *   - 'voteCount' (int): The number of votes for the wish or focus area.
	 *   - 'created' (string): The creation timestamp of the wish or focus area.
	 *   - 'updated' (string): The last updated timestamp of the wish or focus area.
	 *   - 'baseLang' (string): The base language of the wish or focus area (defaults to $lang).
	 */
	public function __construct(
		protected PageIdentity $page,
		protected string $lang,
		array $fields
	) {
		$this->title = $fields['title'] ?? '';
		$this->status = intval( $fields['status'] ?? 0 );
		// Description is not stored in the database and can be null.
		$this->description = $fields['description'] ?? null;
		$this->voteCount = isset( $fields['voteCount'] ) ? intval( $fields['voteCount'] ) : null;
		// We use `?? null` in case the field is not set, and `?: null` to handle blank values.
		$this->created = wfTimestampOrNull( TS_ISO_8601, ( $fields['created'] ?? null ) ?: null );
		$this->updated = wfTimestampOrNull( TS_ISO_8601, ( $fields['updated'] ?? null ) ?: null );
		$this->baseLang = $fields['baseLang'] ?? $lang;
	}

	/**
	 * Get the page for base language of this wish or focus area.
	 *
	 * @return PageIdentity
	 */
	public function getPage(): PageIdentity {
		return $this->page;
	}

	/**
	 * Get the language code of this wish or focus area.
	 *
	 * @return string
	 */
	public function getLang(): string {
		return $this->lang;
	}

	/**
	 * Get the base language code of this wish or focus area.
	 *
	 * @return string
	 */
	public function getBaseLang(): string {
		return $this->baseLang;
	}

	/**
	 * Check if the wish or focus area is in the base language.
	 *
	 * @return bool
	 */
	public function isBaseLang(): bool {
		return $this->lang === $this->baseLang;
	}

	/**
	 * Get the title of this wish or focus area.
	 *
	 * @return string
	 */
	public function getTitle(): string {
		return $this->title;
	}

	/**
	 * Get the status of the wish or focus area.
	 *
	 * @return int One of the $wgCommunityRequestsStatuses IDs.
	 */
	public function getStatus(): int {
		return $this->status;
	}

	/**
	 * Get the description of this wish or focus area.
	 *
	 * @return ?string
	 */
	public function getDescription(): ?string {
		return $this->description;
	}

	/**
	 * Get the number of votes for the wish or focus area.
	 *
	 * @return ?int
	 */
	public function getVoteCount(): ?int {
		return $this->voteCount;
	}

	/**
	 * Get the creation timestamp of the focus area.
	 *
	 * @return ?string
	 */
	public function getCreated(): ?string {
		return $this->created;
	}

	/**
	 * Get the last updated timestamp of the focus area.
	 *
	 * @return ?string
	 */
	public function getUpdated(): ?string {
		return $this->updated;
	}

	/**
	 * Get wishlist entity data as an associative array, ready for
	 * consumption by the ext.communityrequests.intake module.
	 *
	 * @param WishlistConfig $config
	 * @param bool $lowerCaseKeyNames Whether to convert the keys to lower case.
	 *   This needs to be true for use by ApiWishlistBase and its subclasses.
	 * @return array
	 */
	abstract public function toArray( WishlistConfig $config, bool $lowerCaseKeyNames = false ): array;

	/**
	 * Convert the wishlist entity to WikitextContent, ready for storage in the database.
	 * This uses the self::PARAM_* constants to iterate over the expected arguments.
	 * It also transforms numeric IDs to their wikitext representations to make the wikitext
	 * easier to read and edit manually.
	 *
	 * @param WishlistConfig $config
	 * @return WikitextContent
	 */
	abstract public function toWikitext( WishlistConfig $config ): WikitextContent;

	/**
	 * Create a new wishlist entity instance from the given wikitext parameters.
	 * This should only be used on the base language page, specifically by the callers:
	 * - ApiWishEdit::execute()
	 * - WishHookHandler::onLinksUpdateComplete(),
	 * - ApiFocusAreaEdit::execute()
	 * - FocusAreaHookHandler::onLinksUpdateComplete()
	 *
	 * @param PageIdentity $pageTitle
	 * @param string $lang
	 * @param array $params Keys are the PARAM_* constants.
	 * @param WishlistConfig $config
	 * @return AbstractWishlistEntity
	 */
	abstract public static function newFromWikitextParams(
		PageIdentity $pageTitle,
		string $lang,
		array $params,
		WishlistConfig $config
	): self;
}
