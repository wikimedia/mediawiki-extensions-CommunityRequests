<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\FocusArea;

use MediaWiki\Page\PageIdentity;

/**
 * A value object representing a focus area in a particular language.
 */
class FocusArea {

	private PageIdentity $page;
	private string $language;
	private string $shortDescription;
	private string $title;
	private int $voteCount;
	private string $baseLanguage;
	private string $created;
	private string $updated;
	private int $status;

	/**
	 * @param PageIdentity $page The Title of the focus area page.
	 * @param string $language The language (or translated language) of the focus area.
	 * @param array $fields The fields of the focus area, including:
	 *   - 'baseLanguage' (string): The base language of the focus area.
	 *   - 'shortDescription' (string): The short description of the focus area.
	 *   - 'title' (string): The title of the focus area.
	 *   - 'voteCount' (int): The number of votes for the focus area.
	 *   - 'created' (string): The creation timestamp of the focus area.
	 *   - 'updated' (string): The last updated timestamp of the focus area.
	 *   - 'status' (int): The status ID of the focus area.
	 * @throws \InvalidArgumentException If the title or short description is empty.
	 */
	public function __construct(
		PageIdentity $page,
		string $language,
		array $fields
	) {
		$this->page = $page;
		$this->baseLanguage = $fields['baseLanguage'] ?? $language;
		$this->language = $language;
		$this->voteCount = (int)( $fields['voteCount'] ?? 0 );
		$this->status = (int)( $fields['status'] ?? 0 );
		$this->created = $fields['created'] ?? '';
		$this->updated = $fields['updated'] ?? '';

		// title and shortDescription should not be empty
		if ( empty( $fields['title'] ) || empty( $fields['shortDescription'] ) ) {
			throw new \InvalidArgumentException( 'Title and short description cannot be empty.' );
		}

		if ( !$page->getId() ) {
			throw new \InvalidArgumentException( 'Focus area page has not been added to the database yet!' );
		}

		$this->shortDescription = $fields['shortDescription'] ?? '';
		$this->title = $fields['title'] ?? '';
	}

	/**
	 * Get the focus area page.
	 *
	 * @return PageIdentity
	 */
	public function getPage(): PageIdentity {
		return $this->page;
	}

	/**
	 * Get the language for this FocusArea instance.
	 *
	 * @return string
	 */
	public function getLanguage(): string {
		return $this->language;
	}

	/**
	 * Get the base language of the focus area from which translations are made.
	 *
	 * @return string
	 */
	public function getBaseLanguage(): string {
		return $this->baseLanguage;
	}

	/**
	 * Get the focus area title.
	 *
	 * @return string
	 */
	public function getTitle(): string {
		return $this->title;
	}

	/**
	 * Get the focus area short description.
	 *
	 * @return string
	 */
	public function getShortDescription(): string {
		return $this->shortDescription;
	}

	/**
	 * Get the number of votes for the focus area.
	 *
	 * @return int
	 */
	public function getVoteCount(): int {
		return $this->voteCount;
	}

	/**
	 * Get the creation timestamp of the focus area.
	 *
	 * @return string
	 */
	public function getCreated(): string {
		return $this->created;
	}

	/**
	 * Get the last updated timestamp of the focus area.
	 *
	 * @return string
	 */
	public function getUpdated(): string {
		return $this->updated;
	}

	/**
	 * Get the status of the focus area.
	 *
	 * @return int One of the $wgCommunityRequestsStatuses IDs.
	 */
	public function getStatus(): int {
		return $this->status;
	}
}
