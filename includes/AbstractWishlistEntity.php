<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageReference;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Parser\Sanitizer;

/**
 * Shared properties and methods for Wish and FocusArea.
 */
abstract class AbstractWishlistEntity {

	// Constants used for parser function, extension data, and API parameters.
	public const PARAM_STATUS = 'status';
	public const PARAM_STATUSES = 'statuses';
	public const PARAM_TITLE = 'title';
	public const PARAM_DESCRIPTION = 'description';
	public const PARAM_CREATED = 'created';
	public const PARAM_BASE_LANG = 'baselang';

	// These aren't parser function parameters, but are used in APIs and extension data.
	public const PARAM_LANG = 'lang';
	public const PARAM_LANG_INFO = 'langinfo';
	public const PARAM_VOTE_COUNT = 'votecount';
	public const PARAM_WISH_COUNT = 'wishcount';
	public const PARAM_UPDATED = 'updated';
	public const PARAM_BASE_REV_ID = 'baserevid';
	public const PARAM_ENTITY_TYPE = 'entitytype';

	protected string $baselang;
	protected string $title;
	protected int $status;
	protected ?string $description;
	protected ?int $votecount;
	protected ?string $created;
	protected ?string $updated;

	/**
	 * @param PageIdentity $page The page representing the wish or focus area.
	 * @param string $lang The language (or translated language) of the wish or focus area.
	 * @param array $fields The fields, including:
	 *   - 'title' (string): The title of the wish or focus area.
	 *   - 'status' (int): The status ID of the wish or focus area.
	 *   - 'description' (?string): The description of the wish or focus area.
	 *   - 'votecount' (int): The number of votes for the wish or focus area.
	 *   - 'created' (string): The creation timestamp of the wish or focus area.
	 *   - 'updated' (string): The last updated timestamp of the wish or focus area.
	 *   - 'baselang' (string): The base language of the wish or focus area (defaults to $lang).
	 */
	public function __construct(
		protected PageIdentity $page,
		protected string $lang,
		array $fields
	) {
		$this->title = $fields[self::PARAM_TITLE] ?? '';
		$this->status = intval( $fields[self::PARAM_STATUS] ?? 0 );
		// Description is not stored in the database and can be null.
		$this->description = $fields[self::PARAM_DESCRIPTION] ?? null;
		$this->votecount = isset( $fields[self::PARAM_VOTE_COUNT] ) ? intval( $fields[self::PARAM_VOTE_COUNT] ) : null;
		// We use `?? null` in case the field is not set, and `?: null` to handle blank values.
		$this->created = wfTimestampOrNull( TS_ISO_8601, ( $fields[self::PARAM_CREATED] ?? null ) ?: null );
		$this->updated = wfTimestampOrNull( TS_ISO_8601, ( $fields[self::PARAM_UPDATED] ?? null ) ?: null );
		$this->baselang = $fields[self::PARAM_BASE_LANG] ?? $lang;
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
	 * Get a page reference for the translation subpage of this wish or focus area.
	 * This method does not verify if the translation subpage exists, nor does it
	 * validate the language code.
	 *
	 * @return PageReference
	 */
	public function getTranslationSubpage(): PageReference {
		return PageReferenceValue::localReference(
			$this->page->getNamespace(),
			$this->page->getDBkey() . '/' . $this->lang
		);
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
		return $this->baselang;
	}

	/**
	 * Check if the wish or focus area is in the base language.
	 *
	 * @return bool
	 */
	public function isBaseLang(): bool {
		return $this->lang === $this->baselang;
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
		return $this->votecount;
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
	 * Get wishlist entity data as an associative array.
	 *
	 * Keys are the PARAM_* and PROP_* constants defined in this class.
	 *
	 * @param WishlistConfig $config
	 * @return array
	 */
	abstract public function toArray( WishlistConfig $config ): array;

	/**
	 * Convert the wishlist entity to WikitextContent, ready for storage by ApiWishEdit.
	 * This uses the self::PARAM_* constants to iterate over the expected arguments.
	 * It also transforms numeric IDs to their wikitext representations to make the wikitext
	 * easier to read and edit manually.
	 *
	 * @param WishlistConfig $config
	 * @return WikitextContent
	 */
	abstract public function toWikitext( WishlistConfig $config ): WikitextContent;

	/**
	 * Get the sanitized title for use in wikitext storage, escaping common wikitext
	 * syntax, stripping out any <translate> tags, and sanitizing everything else.
	 *
	 * @return string Safe HTML
	 */
	final protected function getTitleSanitizedForWikitext(): string {
		$ret = Sanitizer::removeSomeTags( $this->title, [
			'extraTags' => [ 'translate', 'tvar' ],
			'commentRegex' => '/^T:[0-9]+$/'
		] );
		// This is a subset of Sanitizer::safeEncodeAttribute(), which we can't use here because
		// (a) it would double-escape the HTML, and (b) it relies on the service locator.
		// Other wikitext syntax not listed doesn't seem to cause problems in titles.
		return strtr( $ret, [
			'{' => '&#123;',
			'}' => '&#125;',
			'[' => '&#91;',
			']' => '&#93;',
			'|' => '&#124;',
		] );
	}

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
