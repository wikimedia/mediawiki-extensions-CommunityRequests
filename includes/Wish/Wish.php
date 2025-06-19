<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Extension\Translate\MessageLoading\MessageHandle;
use MediaWiki\Extension\Translate\Utilities\Utilities;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;

/**
 * A value object representing a wish in a particular language.
 */
class Wish {

	// Constants used for parsing and constructing the template invocation.
	public const TAG_ATTR_STATUS = 'status';
	public const TAG_ATTR_TYPE = 'type';
	public const TAG_ATTR_TITLE = 'title';
	public const TAG_ATTR_FOCUS_AREA = 'focusarea';
	public const TAG_ATTR_DESCRIPTION = 'description';
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
	];
	public const TEMPLATE_VALUE_PROJECTS_ALL = 'all';
	public const TEMPLATE_ARRAY_DELIMITER = ',';

	// Wish properties.
	private PageIdentity $pageTitle;
	private string $language;
	private string $baseLanguage;
	private ?UserIdentity $proposer;
	private int $type;
	private int $status;
	private ?int $focusAreaId;
	private int $voteCount;
	private ?string $created;
	private ?string $updated;
	private string $title;
	private array $projects;
	private array $phabTasks;
	private ?string $otherProject;
	// Not stored in the database, but used for constructing the wikitext.
	private ?string $audience;
	private ?string $description;

	/**
	 * @param PageIdentity $pageTitle The title of the wish page. If given a translation subpage,
	 *   the constructor will set self::$pageTitle to the base page title, and self::$baseLanguage
	 *   to the language of the base page.
	 * @param string $language The language (or translated language) of the wish.
	 * @param ?UserIdentity $proposer The user who created the wish. This may be left null for existing
	 *   wishes if the proposer is unknown.
	 * @param array $fields The fields of the wish, including:
	 *   - 'type' (int): The type ID of the wish.
	 *   - 'status' (int): The status ID of the wish.
	 *   - 'focusAreaId' (?int): The ID of the focus area.
	 *   - 'voteCount' (int): The number of votes for the wish.
	 *   - 'created' (?string): The creation timestamp of the wish. If null, it will be fetched
	 *       for existing wishes, and set to the current timestamp for new wishes.
	 *   - 'updated' (?string): The last updated timestamp of the wish.
	 *   - 'title' (string): The title of the wish.
	 *   - 'projects' (array<int>): IDs of $CommunityRequestsProjects associated with the wish.
	 *   - 'otherProject' (?string): The 'other project' associated with the wish.
	 *   - 'phabTasks' (array<int>): IDs of Phabricator tasks associated with the wish.
	 *   - 'baseLang' (string): The base language of the wish (fetched if not provided)
	 *   - 'audience' (?string): The group(s) of users the wish would benefit.
	 *   - 'description' (?string): The description of the wish.
	 */
	public function __construct(
		PageIdentity $pageTitle,
		string $language,
		?UserIdentity $proposer,
		array $fields = []
	) {
		// Use the base non-translated page (if Translate is installed).
		if ( !isset( $fields[ 'baseLang' ] ) &&
			// @phan-suppress-next-line PhanUndeclaredClassReference
			class_exists( MessageHandle::class ) &&
			// @phan-suppress-next-line PhanUndeclaredClassMethod
			Utilities::isTranslationPage( new MessageHandle( Title::castFromPageIdentity( $pageTitle ) ) )
		) {
			$basePage = Title::newFromPageIdentity( $pageTitle )->getBaseTitle();
			if ( $basePage->exists() ) {
				$pageTitle = $basePage;
				$this->baseLanguage = $basePage->getPageLanguage()->getCode();
			}
		} else {
			$this->baseLanguage = $fields['baseLang'] ?? $language;
		}
		$this->pageTitle = $pageTitle;
		$this->language = $language;
		$this->proposer = $proposer;
		$this->type = intval( $fields['type'] ?? 0 );
		$this->status = intval( $fields['status'] ?? 0 );
		$this->focusAreaId = isset( $fields['focusAreaId'] ) ? (int)$fields['focusAreaId'] : null;
		$this->voteCount = intval( $fields['voteCount'] ?? 0 );
		// We use `?? null` in case the field is not set, and `?: null` to handle blank values.
		$this->created = wfTimestampOrNull( TS_ISO_8601, ( $fields['created'] ?? null ) ?: null );
		$this->updated = wfTimestampOrNull( TS_ISO_8601, ( $fields['updated'] ?? null ) ?: null );
		$this->title = $fields['title'] ?? '';
		$this->projects = $fields['projects'] ?? [];
		$this->otherProject = ( $fields['otherProject'] ?? '' ) ?: null;
		$this->phabTasks = $fields['phabTasks'] ?? [];
		$this->audience = $fields['audience'] ?? '';
		$this->description = $fields['description'] ?? '';
	}

	/**
	 * Get the page the wish lives on.
	 *
	 * @return PageIdentity
	 */
	public function getPage(): PageIdentity {
		return $this->pageTitle;
	}

	/**
	 * Get the language for this Wish instance.
	 *
	 * @return string
	 */
	public function getLanguage(): string {
		return $this->language;
	}

	/**
	 * Get the language of the wish from which translations are made.
	 *
	 * @return string
	 */
	public function getBaseLanguage(): string {
		return $this->baseLanguage;
	}

	/**
	 * Check if the wish is in the base language.
	 *
	 * @return bool
	 */
	public function isBaseLanguage(): bool {
		return $this->language === $this->baseLanguage;
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
	 * Get the status of the wish.
	 *
	 * @return int One of the $wgCommunityRequestsStatuses IDs.
	 */
	public function getStatus(): int {
		return $this->status;
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
	 * Get the vote count for the wish.
	 *
	 * @return int
	 */
	public function getVoteCount(): int {
		return $this->voteCount;
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
	 * Get the IDs of the Phabricator tasks associated with the wish.
	 *
	 * @return array<int>
	 */
	public function getPhabTasks(): array {
		return $this->phabTasks;
	}

	/**
	 * Get the creation timestamp of the wish in TS_ISO_8601 format.
	 *
	 * @return ?string
	 */
	public function getCreated(): ?string {
		return $this->created;
	}

	/**
	 * Get the last updated timestamp of the wish in TS_ISO_8601 format.
	 *
	 * @return ?string
	 */
	public function getUpdated(): ?string {
		return $this->updated;
	}

	/** Translatable fields */

	/**
	 * Get the translated title of the wish.
	 *
	 * @return string
	 */
	public function getTitle(): string {
		return $this->title;
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
	 * Get the description of the wish.
	 *
	 * @return ?string
	 */
	public function getDescription(): ?string {
		return $this->description;
	}

	/**
	 * Get wish data as an associative array, ready for consumption by the
	 * ext.communityrequests.intake module.
	 *
	 * @param WishlistConfig $config
	 * @param bool $lowerCaseKeyNames Whether to convert the keys to lower case.
	 *   This needs to be true for ApiWishEdit.
	 * @return array
	 */
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
		];
		if ( $lowerCaseKeyNames ) {
			// Convert keys to lower case for API compatibility.
			$ret = array_change_key_case( $ret, CASE_LOWER );
		}
		return $ret;
	}

	/**
	 * Convert the wish to WikitextContent, ready for storage in the database.
	 * This uses the self::TEMPLATE_ constants and $wgCommunityRequestsWishTemplate['params']
	 * to map the wish's properties to the template parameters. It also transforms numeric IDs
	 * to their wikitext representations to make the wikitext easier to read and edit manually.
	 *
	 * @param TitleValue $template
	 * @param WishlistConfig $config
	 * @return WikitextContent
	 */
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

	/**
	 * Create a new Wish instance from the given wikitext parameters.
	 * This should only be used on the base language wish page,
	 * specifically by the callers SpecialWishlistIntake::onSubmit()
	 * and WishHookHandler::onLinksUpdateComplete().
	 *
	 * @param PageIdentity $pageTitle
	 * @param string $lang
	 * @param ?UserIdentity $proposer
	 * @param array $params Keys are the TAG_ATTR_* constants.
	 * @param WishlistConfig $config
	 * @return Wish
	 */
	public static function newFromWikitextParams(
		PageIdentity $pageTitle,
		string $lang,
		?UserIdentity $proposer,
		array $params,
		WishlistConfig $config
	): self {
		$fields = [
			'type' => $config->getWishTypeIdFromWikitextVal( $params[ self::TAG_ATTR_TYPE ] ?? '' ),
			'status' => $config->getStatusIdFromWikitextVal( $params[ self::TAG_ATTR_STATUS ] ?? '' ),
			'title' => $params[ self::TAG_ATTR_TITLE ] ?? '',
			'focusAreaId' => $params[ self::TAG_ATTR_FOCUS_AREA ] ?? null,
			'created' => $params[ self::TAG_ATTR_CREATED ] ?? null,
			'projects' => self::getProjectsFromCsv( $params[ self::TAG_ATTR_PROJECTS ] ?? '', $config ),
			'otherProject' => $params[ self::TAG_ATTR_OTHER_PROJECT ] ?? null,
			'audience' => $params[ self::TAG_ATTR_AUDIENCE ] ?? '',
			'description' => $params[ self::TAG_ATTR_DESCRIPTION ] ?? '',
			'phabTasks' => self::getPhabTasksFromCsv( $params[ self::TAG_ATTR_PHAB_TASKS ] ?? '' ),
			'baseLang' => $lang,
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
