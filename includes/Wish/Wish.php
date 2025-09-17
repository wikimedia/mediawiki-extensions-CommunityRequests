<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;

/**
 * A value object representing a wish in a particular language.
 */
class Wish extends AbstractWishlistEntity {

	// Constants used for parser function, extension data, and API parameters.
	public const PARAM_TYPE = 'type';
	public const PARAM_FOCUS_AREA = 'focusarea';
	public const PARAM_FOCUS_AREAS = 'focusareas';
	public const PARAM_AUDIENCE = 'audience';
	public const PARAM_TAGS = 'tags';
	public const PARAM_PHAB_TASKS = 'phabtasks';
	public const PARAM_PROPOSER = 'proposer';
	public const PARAM_CREATED = 'created';
	public const PARAMS = [
		self::PARAM_STATUS,
		self::PARAM_TYPE,
		self::PARAM_TITLE,
		self::PARAM_FOCUS_AREA,
		self::PARAM_DESCRIPTION,
		self::PARAM_AUDIENCE,
		self::PARAM_TAGS,
		self::PARAM_PHAB_TASKS,
		self::PARAM_PROPOSER,
		self::PARAM_CREATED,
		self::PARAM_BASE_LANG,
	];
	public const VALUE_ARRAY_DELIMITER = ',';

	// Wish properties.
	private int $type;
	private ?PageIdentity $focusarea;
	private array $tags;
	private array $phabtasks;
	private string $audience;

	/**
	 * @param PageIdentity $page The title of the base language wish page.
	 * @param string $lang The language (or translated language) of the data in $fields.
	 * @param ?UserIdentity $proposer The user who created the wish. This may be left null for existing
	 *   wishes if the proposer is unknown.
	 * @param array $fields The fields of the wish, including:
	 *   - 'type' (int): The type ID of the wish.
	 *   - 'status' (int): The status ID of the wish.
	 *   - 'focusarea' (?PageIdentity): The focus area page the wish is assigned to, or null if not assigned.
	 *   - 'title' (string): The title of the wish.
	 *   - 'description' (?string): The description of the wish.
	 *   - 'tags' (array<int>): IDs of $wgCommunityRequestsTags associated with the wish.
	 *   - 'audience' (?string): The group(s) of users the wish would benefit.
	 *   - 'phabtasks' (array<int>): IDs of Phabricator tasks associated with the wish.
	 *   - 'votecount' (int): The number of votes for the wish.
	 *   - 'created' (?string): The creation timestamp of the wish. If null, it will be fetched
	 *        for existing wishes, and set to the current timestamp for new wishes.
	 *   - 'updated' (?string): The last updated timestamp of the wish.
	 *   - 'baselang' (?string): The base language of the wish (defaults to $lang)
	 */
	public function __construct(
		protected PageIdentity $page,
		protected string $lang,
		private readonly ?UserIdentity $proposer,
		array $fields = []
	) {
		parent::__construct( $page, $lang, $fields );
		$this->type = intval( $fields[self::PARAM_TYPE] ?? 0 );
		$this->focusarea = $fields[self::PARAM_FOCUS_AREA] ?? null;
		$this->tags = $fields[self::PARAM_TAGS] ?? [];
		$this->phabtasks = $fields[self::PARAM_PHAB_TASKS] ?? [];
		$this->audience = $fields[self::PARAM_AUDIENCE] ?? '';
	}

	/**
	 * Get the user who created the wish.
	 *
	 * @return ?UserIdentity
	 */
	public function getProposer(): ?UserIdentity {
		return $this->proposer;
	}

	/**
	 * Get the type of the wish.
	 *
	 * @return int One of the $wgCommunityRequestsWishTypes IDs
	 */
	public function getType(): int {
		return $this->type;
	}

	/**
	 * Get the focus area page associated with the wish.
	 *
	 * @return ?PageIdentity
	 */
	public function getFocusAreaPage(): ?PageIdentity {
		return $this->focusarea;
	}

	/**
	 * Get the IDs of the tags associated with the wish.
	 *
	 * @return array<int>
	 */
	public function getTags(): array {
		return $this->tags;
	}

	/**
	 * Get the audience of the wish, i.e. the group(s) of users the wish would benefit.
	 *
	 * @return string
	 */
	public function getAudience(): string {
		return $this->audience;
	}

	/**
	 * Get the IDs of the Phabricator tasks associated with the wish.
	 *
	 * @return array<int>
	 */
	public function getPhabTasks(): array {
		return $this->phabtasks;
	}

	/** @inheritDoc */
	public function toArray( WishlistConfig $config ): array {
		return [
			self::PARAM_STATUS => $config->getStatusWikitextValFromId( $this->status ),
			self::PARAM_TYPE => $config->getWishTypeWikitextValFromId( $this->type ),
			self::PARAM_TITLE => $this->title,
			self::PARAM_FOCUS_AREA => $config->getEntityWikitextVal( $this->getFocusAreaPage() ) ?: '',
			self::PARAM_DESCRIPTION => $this->description,
			self::PARAM_AUDIENCE => $this->audience,
			self::PARAM_TAGS => array_map(
				static fn ( $id ) => $config->getTagWikitextValFromId( $id ),
				$this->tags
			),
			self::PARAM_PHAB_TASKS => array_map( static fn ( $t ) => "T$t", $this->phabtasks ),
			self::PARAM_PROPOSER => $this->proposer?->getName(),
			self::PARAM_VOTE_COUNT => $this->votecount,
			self::PARAM_CREATED => $this->created,
			self::PARAM_UPDATED => $this->updated,
			self::PARAM_BASE_LANG => $this->baselang,
		];
	}

	/** @inheritDoc */
	public function toWikitext( WishlistConfig $config ): WikitextContent {
		$wikitext = "{{#CommunityRequests: wish\n";

		foreach ( self::PARAMS as $param ) {
			// Match ID values to their wikitext representations, as defined by site configuration.
			$value = match ( $param ) {
				self::PARAM_STATUS => $config->getStatusWikitextValFromId( $this->status ),
				self::PARAM_TYPE => $config->getWishTypeWikitextValFromId( $this->type ),
				self::PARAM_TAGS => array_map(
					static fn ( $id ) => $config->getTagWikitextValFromId( $id ),
					$this->tags
				),
				self::PARAM_PHAB_TASKS => array_map( static fn ( $id ) => "T$id", $this->phabtasks ),
				self::PARAM_FOCUS_AREA => $config->getEntityWikitextVal( $this->focusarea ) ?: '',
				self::PARAM_CREATED => MWTimestamp::convert( TS_ISO_8601, $this->created ),
				self::PARAM_PROPOSER => $this->proposer ? $this->proposer->getName() : '',
				self::PARAM_BASE_LANG => $this->baselang,
				default => $this->{ $param },
			};

			if ( is_array( $value ) ) {
				// Convert arrays to a comma-separated string.
				$value = implode( WishStore::ARRAY_DELIMITER_WIKITEXT, $value );
			}

			// Append wikitext.
			$value = trim( (string)$value );
			$wikitext .= "| $param = $value\n";
		}

		$wikitext .= "}}\n";

		return new WikitextContent( $wikitext );
	}

	/** @inheritDoc */
	public static function newFromWikitextParams(
		PageIdentity $pageTitle,
		string $lang,
		array $params,
		WishlistConfig $config,
		?UserIdentity $proposer = null
	): self {
		$faValue = $config->getFocusAreaPageRefFromWikitextVal( $params[self::PARAM_FOCUS_AREA] ?? '' );
		$fields = [
			self::PARAM_TYPE => $config->getWishTypeIdFromWikitextVal( $params[self::PARAM_TYPE] ?? '' ),
			self::PARAM_STATUS => $config->getStatusIdFromWikitextVal( $params[self::PARAM_STATUS] ?? '' ),
			self::PARAM_TITLE => $params[self::PARAM_TITLE] ?? '',
			// TODO: It would be better to avoid use of Title here.
			self::PARAM_FOCUS_AREA => $faValue ? Title::newFromPageReference( $faValue ) : null,
			self::PARAM_DESCRIPTION => $params[self::PARAM_DESCRIPTION] ?? '',
			self::PARAM_TAGS => self::getTagsFromCsv( $params[self::PARAM_TAGS] ?? '', $config ),
			self::PARAM_AUDIENCE => $params[self::PARAM_AUDIENCE] ?? '',
			self::PARAM_PHAB_TASKS => self::getPhabTasksFromCsv( $params[self::PARAM_PHAB_TASKS] ?? '' ),
			self::PARAM_CREATED => $params[self::PARAM_CREATED] ?? null,
			self::PARAM_BASE_LANG => $params[self::PARAM_BASE_LANG] ?? $lang,
			self::PARAM_VOTE_COUNT => $params[self::PARAM_VOTE_COUNT] ?? null,
		];

		return new self( $pageTitle, $lang, $proposer, $fields );
	}

	/**
	 * Given a comma-separated wikitext value for tags, get the tag IDs.
	 *
	 * @param string $csvTags
	 * @param WishlistConfig $config
	 * @return int[]
	 */
	public static function getTagsFromCsv( string $csvTags, WishlistConfig $config ): array {
		return static::getFromCsv(
			$csvTags,
			static fn ( $name ) => $config->getTagIdFromWikitextVal( $name )
		);
	}

	/**
	 * Given a comma-separated wikitext value for Phabricator tasks, get the task IDs as integers.
	 *
	 * @param string $csvTasks
	 * @return int[] The task IDs.
	 */
	public static function getPhabTasksFromCsv( string $csvTasks ): array {
		return static::getFromCsv(
			$csvTasks,
			static fn ( $id ) => preg_match( '/^T?(\d+)$/', trim( $id ), $matches ) ?
				( $matches[1] ? (int)$matches[1] : null ) :
				null
		);
	}

	/**
	 * Generic helper to parse a comma-separated wikitext value into an array of values using a mapping function.
	 *
	 * @param string $csv The comma-separated wikitext value.
	 * @param callable $mapFunc A function that takes individual values and returns them mapped to the desired type.
	 * @return int[] The array of IDs.
	 */
	public static function getFromCsv( string $csv, callable $mapFunc ): array {
		if ( trim( $csv ) === '' ) {
			return [];
		}
		return array_values( array_filter(
			array_map(
				$mapFunc,
				explode( self::VALUE_ARRAY_DELIMITER, $csv )
			),
			static fn ( $id ) => $id !== null
		) );
	}
}
