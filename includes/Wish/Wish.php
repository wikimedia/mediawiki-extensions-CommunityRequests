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
	public const PARAM_AUDIENCE = 'audience';
	public const PARAM_PROJECTS = 'projects';
	public const PARAM_OTHER_PROJECT = 'otherproject';
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
		self::PARAM_PROJECTS,
		self::PARAM_OTHER_PROJECT,
		self::PARAM_PHAB_TASKS,
		self::PARAM_PROPOSER,
		self::PARAM_CREATED,
		self::PARAM_BASE_LANG,
	];
	public const VALUE_PROJECTS_ALL = 'all';
	public const VALUE_ARRAY_DELIMITER = ',';

	// Wish properties.
	private int $type;
	private ?PageIdentity $focusarea;
	private array $projects;
	private array $phabtasks;
	private ?string $otherproject;
	private ?string $audience;

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
	 *   - 'projects' (array<int>): IDs of $wgCommunityRequestsProjects associated with the wish.
	 *   - 'otherproject' (?string): The 'other project' associated with the wish.
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
		$this->projects = $fields[self::PARAM_PROJECTS] ?? [];
		$this->otherproject = ( $fields[self::PARAM_OTHER_PROJECT] ?? '' ) ?: null;
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
	 * Get the IDs of the projects associated with the wish.
	 *
	 * @return array<int>
	 */
	public function getProjects(): array {
		return $this->projects;
	}

	/**
	 * Get the translated value of the 'other project' field.
	 *
	 * @return ?string
	 */
	public function getOtherProject(): ?string {
		return $this->otherproject;
	}

	/**
	 * Get the audience of the wish, i.e. the group(s) of users the wish would benefit.
	 *
	 * @return ?string
	 */
	public function getAudience(): ?string {
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
			self::PARAM_PROJECTS => $config->getProjectsWikitextValsFromIds( $this->projects ),
			self::PARAM_OTHER_PROJECT => (string)$this->otherproject,
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
				self::PARAM_PROJECTS => $config->getProjectsWikitextValsFromIds( $this->projects ),
				self::PARAM_PHAB_TASKS => array_map( static fn ( $id ) => "T$id", $this->phabtasks ),
				self::PARAM_FOCUS_AREA => $config->getEntityWikitextVal( $this->focusarea ) ?: '',
				self::PARAM_CREATED => MWTimestamp::convert( TS_ISO_8601, $this->created ),
				self::PARAM_PROPOSER => $this->proposer ? $this->proposer->getName() : '',
				self::PARAM_BASE_LANG => $this->baselang,
				default => $this->{ $param },
			};

			if ( is_array( $value ) ) {
				// Convert arrays to a comma-separated string.
				$value = implode( self::VALUE_ARRAY_DELIMITER, $value );
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
			self::PARAM_PROJECTS => self::getProjectsFromCsv( $params[self::PARAM_PROJECTS] ?? '', $config ),
			self::PARAM_OTHER_PROJECT => $params[self::PARAM_OTHER_PROJECT] ?? null,
			self::PARAM_AUDIENCE => $params[self::PARAM_AUDIENCE] ?? '',
			self::PARAM_PHAB_TASKS => self::getPhabTasksFromCsv( $params[self::PARAM_PHAB_TASKS] ?? '' ),
			self::PARAM_CREATED => $params[self::PARAM_CREATED] ?? null,
			self::PARAM_BASE_LANG => $params[self::PARAM_BASE_LANG] ?? $lang,
			self::PARAM_VOTE_COUNT => $params[self::PARAM_VOTE_COUNT] ?? null,
		];

		return new self( $pageTitle, $lang, $proposer, $fields );
	}

	/**
	 * Given a comma-separated wikitext value for projects, get the project IDs.
	 *
	 * @param string $csvProjects
	 * @param WishlistConfig $config
	 * @return int[]
	 */
	public static function getProjectsFromCsv( string $csvProjects, WishlistConfig $config ): array {
		if ( $csvProjects === self::VALUE_PROJECTS_ALL ) {
			// If the value is 'all', return all project IDs.
			return array_values( array_map( static fn ( $p ) => (int)$p['id'], $config->getProjects() ) );
		}

		// @phan-suppress-next-line PhanTypeMismatchReturn
		return array_values(
			array_filter(
				array_map(
					static fn ( $name ) => $config->getProjectIdFromWikitextVal( $name ),
					explode( self::VALUE_ARRAY_DELIMITER, $csvProjects )
				),
				static fn ( $id ) => $id !== null
			)
		);
	}

	/**
	 * Given a comma-separated wikitext value for Phabricator tasks, get the task IDs as integers.
	 *
	 * @param string $csvTasks
	 * @return int[] The task IDs.
	 */
	public static function getPhabTasksFromCsv( string $csvTasks ): array {
		$tasks = [];
		$taskIds = explode( self::VALUE_ARRAY_DELIMITER, $csvTasks );
		foreach ( $taskIds as $id ) {
			$matches = [];
			preg_match( '/^T?(\d+)$/', trim( $id ), $matches );
			if ( isset( $matches[1] ) ) {
				$tasks[] = (int)$matches[1];
			}
		}
		return $tasks;
	}
}
