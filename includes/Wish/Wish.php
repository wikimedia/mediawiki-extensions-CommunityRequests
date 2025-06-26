<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;

/**
 * A value object representing a wish in a particular language.
 */
class Wish extends AbstractWishlistEntity {

	// Constants used for parsing and constructing the template invocation.
	public const TAG_ATTR_TYPE = 'type';
	public const TAG_ATTR_FOCUS_AREA = 'focusarea';
	public const TAG_ATTR_AUDIENCE = 'audience';
	public const TAG_ATTR_PROJECTS = 'projects';
	public const TAG_ATTR_OTHER_PROJECT = 'otherproject';
	public const TAG_ATTR_PHAB_TASKS = 'phabtasks';
	public const TAG_ATTR_PROPOSER = 'proposer';
	public const TAG_ATTR_CREATED = 'created';
	public const TAG_ATTRS = [
		self::TAG_ATTR_STATUS,
		self::TAG_ATTR_TYPE,
		self::TAG_ATTR_TITLE,
		self::TAG_ATTR_FOCUS_AREA,
		self::TAG_ATTR_DESCRIPTION,
		self::TAG_ATTR_AUDIENCE,
		self::TAG_ATTR_PROJECTS,
		self::TAG_ATTR_OTHER_PROJECT,
		self::TAG_ATTR_PHAB_TASKS,
		self::TAG_ATTR_PROPOSER,
		self::TAG_ATTR_CREATED,
		self::TAG_ATTR_BASE_LANG,
	];
	public const TEMPLATE_VALUE_PROJECTS_ALL = 'all';
	public const TEMPLATE_ARRAY_DELIMITER = ',';

	// Wish properties.
	private int $type;
	private ?int $focusAreaId;
	private array $projects;
	private array $phabTasks;
	private ?string $otherProject;
	private ?string $audience;

	/**
	 * @param PageIdentity $page The title of the base language wish page.
	 * @param string $lang The language (or translated language) of the data in $fields.
	 * @param ?UserIdentity $proposer The user who created the wish. This may be left null for existing
	 *   wishes if the proposer is unknown.
	 * @param array $fields The fields of the wish, including:
	 *   - 'type' (int): The type ID of the wish.
	 *   - 'status' (int): The status ID of the wish.
	 *   - 'focusAreaId' (?int): The ID of the focus area.
	 *   - 'title' (string): The title of the wish.
	 *   - 'description' (?string): The description of the wish.
	 *   - 'projects' (array<int>): IDs of $CommunityRequestsProjects associated with the wish.
	 *   - 'otherProject' (?string): The 'other project' associated with the wish.
	 *   - 'phabTasks' (array<int>): IDs of Phabricator tasks associated with the wish.
	 *   - 'audience' (?string): The group(s) of users the wish would benefit.
	 *   - 'phabTasks' (array<int>): IDs of Phabricator tasks associated with the wish.
	 *   - 'voteCount' (int): The number of votes for the wish.
	 *   - 'created' (?string): The creation timestamp of the wish. If null, it will be fetched
	 *        for existing wishes, and set to the current timestamp for new wishes.
	 *   - 'updated' (?string): The last updated timestamp of the wish.
	 *   - 'baseLang' (?string): The base language of the wish (defaults to $lang)
	 */
	public function __construct(
		protected PageIdentity $page,
		protected string $lang,
		private readonly ?UserIdentity $proposer,
		array $fields = []
	) {
		parent::__construct( $page, $lang, $fields );
		$this->type = intval( $fields['type'] ?? 0 );
		$this->focusAreaId = isset( $fields['focusAreaId'] ) ? (int)$fields['focusAreaId'] : null;
		$this->projects = $fields['projects'] ?? [];
		$this->otherProject = ( $fields['otherProject'] ?? '' ) ?: null;
		$this->phabTasks = $fields['phabTasks'] ?? [];
		$this->audience = $fields['audience'] ?? '';
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
	 * Get the ID of the focus area ID the wish is assigned to.
	 *
	 * @return ?int
	 */
	public function getFocusAreaId(): ?int {
		return $this->focusAreaId;
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
		return $this->otherProject;
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
		return $this->phabTasks;
	}

	/** @inheritDoc */
	public function toArray( WishlistConfig $config, bool $lowerCaseKeyNames = false ): array {
		$ret = [
			'status' => $config->getStatusWikitextValFromId( $this->status ),
			'type' => $config->getWishTypeWikitextValFromId( $this->type ),
			'title' => $this->title,
			// FIXME: Focus area is not yet implemented.
			'focusArea' => null,
			'description' => $this->description,
			'audience' => $this->audience,
			'projects' => $config->getProjectsWikitextValsFromIds( $this->projects ),
			'otherProject' => (string)$this->otherProject,
			'phabTasks' => array_map( static fn ( $t ) => "T$t", $this->phabTasks ),
			'proposer' => $this->proposer?->getName(),
			'created' => $this->created,
			'baseLang' => $this->baseLang,
		];
		if ( $lowerCaseKeyNames ) {
			// Convert keys to lower case for API compatibility.
			$ret = array_change_key_case( $ret, CASE_LOWER );
		}
		return $ret;
	}

	/** @inheritDoc */
	public function toWikitext( TitleValue $template, WishlistConfig $config ): WikitextContent {
		$templateCall = $template->getNamespace() === NS_TEMPLATE ?
			$template->getText() :
			':' . $config->getWishTemplatePage();

		$wikitext = "{{" . $templateCall . "\n";

		foreach ( self::TAG_ATTRS as $attr ) {
			$param = $config->getWishTemplateParams()[ $attr ];

			// Match ID values to their wikitext representations, as defined by site configuration.
			$value = match ( $attr ) {
				self::TAG_ATTR_PROJECTS => $config->getProjectsWikitextValsFromIds( $this->projects ),
				self::TAG_ATTR_OTHER_PROJECT => $this->otherProject ?? '',
				self::TAG_ATTR_PHAB_TASKS => array_map( static fn ( $id ) => "T$id", $this->phabTasks ),
				self::TAG_ATTR_STATUS => $config->getStatusWikitextValFromId( $this->status ),
				self::TAG_ATTR_TYPE => $config->getWishTypeWikitextValFromId( $this->type ),
				self::TAG_ATTR_FOCUS_AREA => $this->focusAreaId,
				self::TAG_ATTR_CREATED => MWTimestamp::convert( TS_ISO_8601, $this->created ),
				self::TAG_ATTR_PROPOSER => $this->proposer ? $this->proposer->getName() : '',
				self::TAG_ATTR_BASE_LANG => $this->baseLang,
				default => $this->{ $attr },
			};

			if ( is_array( $value ) ) {
				// Convert arrays to a comma-separated string.
				$value = implode( self::TEMPLATE_ARRAY_DELIMITER, $value );
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
		$fields = [
			'type' => $config->getWishTypeIdFromWikitextVal( $params[ self::TAG_ATTR_TYPE ] ?? '' ),
			'status' => $config->getStatusIdFromWikitextVal( $params[ self::TAG_ATTR_STATUS ] ?? '' ),
			'title' => $params[ self::TAG_ATTR_TITLE ] ?? '',
			'focusAreaId' => $params[ self::TAG_ATTR_FOCUS_AREA ] ?? null,
			'description' => $params[ self::TAG_ATTR_DESCRIPTION ] ?? '',
			'projects' => self::getProjectsFromCsv( $params[ self::TAG_ATTR_PROJECTS ] ?? '', $config ),
			'otherProject' => $params[ self::TAG_ATTR_OTHER_PROJECT ] ?? null,
			'audience' => $params[ self::TAG_ATTR_AUDIENCE ] ?? '',
			'phabTasks' => self::getPhabTasksFromCsv( $params[ self::TAG_ATTR_PHAB_TASKS ] ?? '' ),
			'created' => $params[ self::TAG_ATTR_CREATED ] ?? null,
			'baseLang' => $params[ self::TAG_ATTR_BASE_LANG ] ?? $lang,
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
		if ( $csvProjects === self::TEMPLATE_VALUE_PROJECTS_ALL ) {
			// If the value is 'all', return all project IDs.
			return array_values( array_map( static fn ( $p ) => (int)$p[ 'id' ], $config->getProjects() ) );
		}

		// @phan-suppress-next-line PhanTypeMismatchReturn
		return array_values(
			array_filter(
				array_map(
					static fn ( $name ) => $config->getProjectIdFromWikitextVal( $name ),
					explode( self::TEMPLATE_ARRAY_DELIMITER, $csvProjects )
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
		$taskIds = explode( self::TEMPLATE_ARRAY_DELIMITER, $csvTasks );
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
