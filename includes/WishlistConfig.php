<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Config\ConfigException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Language\LanguageCode;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleParser;

/**
 * Service that abstracts retrieving configuration values, with methods to
 * transform between string values used in wikitext, IDs used in the database,
 * and labels used in the UI.
 *
 * @newable
 */
class WishlistConfig {

	public const CONFIG_ENABLED = 'CommunityRequestsEnable';
	public const CONFIG_HOMEPAGE = 'CommunityRequestsHomepage';
	public const CONFIG_WISH_CATEGORY = 'CommunityRequestsWishCategory';
	public const CONFIG_WISH_PAGE_PREFIX = 'CommunityRequestsWishPagePrefix';
	public const CONFIG_FOCUS_AREA_PAGE_PREFIX = 'CommunityRequestsFocusAreaPagePrefix';
	public const CONFIG_WISH_INDEX_PAGE = 'CommunityRequestsWishIndexPage';
	public const CONFIG_WISH_TEMPLATE = 'CommunityRequestsWishTemplate';
	public const CONFIG_WISH_TYPES = 'CommunityRequestsWishTypes';
	public const CONFIG_PROJECTS = 'CommunityRequestsProjects';
	public const CONFIG_STATUSES = 'CommunityRequestsStatuses';
	public const CONSTRUCTOR_OPTIONS = [
		self::CONFIG_ENABLED,
		self::CONFIG_HOMEPAGE,
		self::CONFIG_WISH_CATEGORY,
		self::CONFIG_WISH_PAGE_PREFIX,
		self::CONFIG_FOCUS_AREA_PAGE_PREFIX,
		self::CONFIG_WISH_INDEX_PAGE,
		self::CONFIG_WISH_TEMPLATE,
		self::CONFIG_WISH_TYPES,
		self::CONFIG_PROJECTS,
		self::CONFIG_STATUSES
	];

	private bool $enabled;
	private string $homepage;
	private string $wishCategory;
	private string $wishPagePrefix;
	private string $focusAreaPagePrefix;
	private string $wishIndexPage;
	private array $wishTemplate;
	private array $wishTypes;
	private array $projects;
	private array $statuses;

	public function __construct(
		ServiceOptions $config,
		private readonly TitleParser $titleParser,
		private readonly TitleFormatter $titleFormatter
	) {
		$this->enabled = $config->get( self::CONFIG_ENABLED );
		$this->homepage = $config->get( self::CONFIG_HOMEPAGE );
		$this->wishCategory = $config->get( self::CONFIG_WISH_CATEGORY );
		$this->wishPagePrefix = $config->get( self::CONFIG_WISH_PAGE_PREFIX );
		$this->focusAreaPagePrefix = $config->get( self::CONFIG_FOCUS_AREA_PAGE_PREFIX );
		$this->wishIndexPage = $config->get( self::CONFIG_WISH_INDEX_PAGE );
		$this->wishTemplate = $config->get( self::CONFIG_WISH_TEMPLATE );
		$this->wishTypes = $config->get( self::CONFIG_WISH_TYPES );
		$this->projects = $config->get( self::CONFIG_PROJECTS );
		$this->statuses = $config->get( self::CONFIG_STATUSES );
	}

	// Getters

	public function isEnabled(): bool {
		return $this->enabled;
	}

	public function getHomepage(): string {
		return $this->homepage;
	}

	public function getWishCategory(): string {
		return $this->wishCategory;
	}

	public function getWishPagePrefix(): string {
		return $this->wishPagePrefix;
	}

	public function getWishIndexPage(): string {
		return $this->wishIndexPage;
	}

	public function getWishTemplate(): array {
		return $this->wishTemplate;
	}

	public function getWishTemplatePage(): string {
		return $this->wishTemplate[ 'page' ];
	}

	public function getWishTemplateParams(): array {
		return $this->wishTemplate[ 'params' ];
	}

	public function getWishTypes(): array {
		return $this->wishTypes;
	}

	public function getFocusAreaPagePrefix(): string {
		return $this->focusAreaPagePrefix;
	}

	public function getProjects(): array {
		return $this->projects;
	}

	public function getStatuses(): array {
		return $this->statuses;
	}

	// Helpers

	/**
	 * Check if the given PageIdentity could be a wish page based on its title.
	 *
	 * @param ?PageIdentity $identity
	 * @return bool
	 */
	public function isWishPage( ?PageIdentity $identity ): bool {
		return $this->titleStartsWith( $identity, $this->wishPagePrefix );
	}

	/**
	 * Check if the given PageIdentity could be a focus area page based on its title.
	 *
	 * @param ?PageIdentity $identity
	 * @return bool
	 */
	public function isFocusAreaPage( ?PageIdentity $identity ): bool {
		return $this->titleStartsWith( $identity, $this->focusAreaPagePrefix );
	}

	/**
	 * Check if the given PageIdentity could be a wish or focus area page based on its title.
	 *
	 * @param ?PageIdentity $identity
	 * @return bool
	 */
	public function isWishOrFocusAreaPage( ?PageIdentity $identity ): bool {
		return $this->isWishPage( $identity ) || $this->isFocusAreaPage( $identity );
	}

	private function titleStartsWith( ?PageIdentity $identity, string $prefix ): bool {
		if ( !$identity ) {
			return false;
		}
		$pagePrefix = $this->titleParser->parseTitle( $prefix );

		$identityStr = $this->titleFormatter->getPrefixedDBkey( $identity );
		$pagePrefixStr = $this->titleFormatter->getPrefixedDBkey( $pagePrefix );
		$remaining = substr( $identityStr, strlen( $pagePrefixStr ) );

		if ( str_starts_with( $identityStr, $pagePrefixStr ) && is_numeric( $remaining ) ) {
			return true;
		}

		// Remove each numeric character from the beginning of $remaining
		$remaining = ltrim( $remaining, '0123456789' );

		// Remove leading slash.
		$remaining = ltrim( $remaining, '/' );

		// Check if the $remaining is probably a valid language code.
		return LanguageCode::isWellFormedLanguageTag( $remaining );
	}

	// IDs and labels from wikitext values

	/**
	 * Get the ID of a wish type from its wikitext value.
	 *
	 * @param string $type
	 * @return int The ID of the wish type.
	 */
	public function getWishTypeIdFromWikitextVal( string $type ): int {
		return $this->getIdFromWikitextVal( $type, $this->wishTypes );
	}

	/**
	 * Get the label of a wish type from its wikitext value.
	 *
	 * @param string $type
	 * @return ?string The label of the wish type, or null if not found.
	 */
	public function getWishTypeLabelFromWikitextVal( string $type ): ?string {
		$type = trim( $type );
		return $this->wishTypes[ $type ][ 'label' ] ?? null;
	}

	/**
	 * Get the ID of a project from its wikitext value.
	 *
	 * @param string $project
	 * @return ?int The ID of the project or null if not found.
	 */
	public function getProjectIdFromWikitextVal( string $project ): ?int {
		$project = trim( $project );
		if ( isset( $this->projects[ $project ] ) ) {
			return (int)$this->projects[ $project ][ 'id' ];
		}
		return null;
	}

	/**
	 * Get the label of a project from its wikitext value.
	 *
	 * @param string $project
	 * @return ?string The label of the project, or null if not found.
	 */
	public function getProjectLabelFromWikitextVal( string $project ): ?string {
		if ( $project === Wish::TEMPLATE_VALUE_PROJECTS_ALL ) {
			return 'communityrequests-project-all-projects';
		}
		$project = trim( $project );
		return $this->projects[ $project ][ 'label' ] ?? null;
	}

	/**
	 * Get the ID of a status from its wikitext value.
	 *
	 * @param string $status
	 * @return int The ID of the status.
	 */
	public function getStatusIdFromWikitextVal( string $status ): int {
		return $this->getIdFromWikitextVal( $status, $this->statuses );
	}

	/**
	 * Get the label of a status from its wikitext value.
	 *
	 * @param string $status
	 * @return ?string The label of the status, or null if not found.
	 */
	public function getStatusLabelFromWikitextVal( string $status ): ?string {
		$status = trim( $status );
		return $this->statuses[ $status ][ 'label' ] ?? null;
	}

	/**
	 * Get the ID of a status or type from its wikitext value.
	 * If the value is not found, it will return the ID of the entry with 'default' set to true.
	 *
	 * @param string $val The wikitext value to look up.
	 * @param array $config The configuration array for statuses or types.
	 * @return int The ID of the status or type.
	 * @throws ConfigException If the value is not found and no default is set.
	 */
	private function getIdFromWikitextVal( string $val, array $config ): int {
		$val = trim( $val );
		if ( isset( $config[ $val ] ) ) {
			return (int)$config[ $val ][ 'id' ];
		}
		// If the value is not found, return the default value.
		foreach ( $config as $item ) {
			if ( $item[ 'default' ] ?? false ) {
				return (int)$item[ 'id' ];
			}
		}
		throw new ConfigException(
			"Value '$val' not found in configuration, and no default is set."
		);
	}

	// Wikitext values from IDs

	/**
	 * Get the wikitext value from a wish type ID.
	 *
	 * @param int $id
	 * @return string
	 * @throws ConfigException If the ID is not found in the configuration.
	 */
	public function getWishTypeWikitextValFromId( int $id ): string {
		return $this->getWikitextValFromId( $id, $this->wishTypes );
	}

	/**
	 * Get the wikitext value from a project ID.
	 *
	 * @param int $id
	 * @return string
	 * @throws ConfigException If the ID is not found in the configuration.
	 */
	public function getProjectWikitextValFromId( int $id ): string {
		return $this->getWikitextValFromId( $id, $this->projects );
	}

	/**
	 * Get the wikitext values from a list of project IDs.
	 *
	 * @param int[] $ids
	 * @return string[]
	 * @throws ConfigException If any ID is not found in the configuration.
	 */
	public function getProjectsWikitextValsFromIds( array $ids ): array {
		$allProjectIds = array_map(
			static fn ( $p ) => (int)$p[ 'id' ],
			$this->projects
		);
		$isAllProjects = array_diff( $allProjectIds, $ids ) === [];
		if ( $isAllProjects ) {
			return [ Wish::TEMPLATE_VALUE_PROJECTS_ALL ];
		}
		return array_map(
			fn ( $id ) => $this->getProjectWikitextValFromId( $id ),
			$ids
		);
	}

	/**
	 * Get the wikitext value from a status ID.
	 *
	 * @param int $id
	 * @return string
	 * @throws ConfigException If the ID is not found in the configuration.
	 */
	public function getStatusWikitextValFromId( int $id ): string {
		return $this->getWikitextValFromId( $id, $this->statuses );
	}

	/**
	 * Get the wikitext value from an enum-esque configuration array.
	 * TODO: Use array_find_key() once PHP 8.4 is the minimum version.
	 *
	 * @param int $id
	 * @param array $config
	 * @return string
	 * @throws ConfigException If the ID is not found in the configuration.
	 */
	private function getWikitextValFromId( int $id, array $config ): string {
		foreach ( $config as $key => $item ) {
			if ( (int)$item[ 'id' ] === $id ) {
				return $key;
			}
		}
		throw new ConfigException( "ID '$id' not found in configuration." );
	}
}
