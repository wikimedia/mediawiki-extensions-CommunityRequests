<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Extension\Translate\MessageLoading\MessageHandle;
use MediaWiki\Extension\Translate\Utilities\Utilities;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;

/**
 * A value object representing a wish in a particular language.
 */
class Wish {

	private PageIdentity $pageTitle;
	private string $language;
	private string $baseLanguage;
	private UserIdentity $user;
	private int $type;
	private int $status;
	private ?int $focusAreaId;
	private int $voteCount;
	private string $created;
	private string $updated;
	private string $title;
	private array $projects;
	private array $phabTasks;
	private ?string $otherProject;

	/**
	 * @param PageIdentity $pageTitle The title of the wish page. If given a translation subpage,
	 *   the constructor will set self::$pageTitle to the base page title, and self::$baseLanguage
	 *   to the language of the base page.
	 * @param string $language The language (or translated language) of the wish.
	 * @param UserIdentity $user The user who created the wish.
	 * @param array $fields The fields of the wish, including:
	 *   - 'type' (int): The type ID of the wish.
	 *   - 'status' (int): The status ID of the wish.
	 *   - 'focusAreaId' (?int): The ID of the focus area.
	 *   - 'voteCount' (int): The number of votes for the wish.
	 *   - 'created' (string): The creation timestamp of the wish.
	 *   - 'updated' (string): The last updated timestamp of the wish.
	 *   - 'title' (string): The title of the wish.
	 *   - 'projects' (array<int>): IDs of $CommunityRequestsProjects associated with the wish.
	 *   - 'otherProject' (?string): The 'other project' associated with the wish.
	 *   - 'phabTasks' (array<int>): IDs of Phabricator tasks associated with the wish.
	 *   - 'baseLang' (string): The base language of the wish (fetched if not provided)
	 */
	public function __construct( PageIdentity $pageTitle, string $language, UserIdentity $user, array $fields ) {
		// Use the base non-translated page (if Translate is installed).
		if ( !isset( $fields[ 'baselang' ] ) &&
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
		$this->user = $user;
		$this->type = intval( $fields['type'] ?? 0 );
		$this->status = intval( $fields['status'] ?? 0 );
		$this->focusAreaId = isset( $fields['focusAreaId'] ) ? (int)$fields['focusAreaId'] : null;
		$this->voteCount = intval( $fields['voteCount'] ?? 0 );
		$this->created = $fields['created'] ?? '';
		$this->updated = $fields['updated'] ?? $this->created;
		$this->title = $fields['title'] ?? '';
		$this->projects = $fields['projects'] ?? [];
		$this->otherProject = $fields['otherProject'] ?? null;
		$this->phabTasks = $fields['phabTasks'] ?? [];
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
	 * Get the user who created the wish.
	 *
	 * @return UserIdentity
	 */
	public function getUser(): UserIdentity {
		return $this->user;
	}

	/**
	 * Get the type of the wish.
	 *
	 * @return int
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
	 * Set the vote count for the wish.
	 *
	 * @param int $voteCount The new vote count.
	 */
	public function setVoteCount( int $voteCount ): void {
		$this->voteCount = $voteCount;
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
	 * Get the creation timestamp of the wish.
	 *
	 * @return string
	 */
	public function getCreated(): string {
		return $this->created;
	}

	/**
	 * Get the last updated timestamp of the wish.
	 *
	 * @return string
	 */
	public function getUpdated(): string {
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
}
