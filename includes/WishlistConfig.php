<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Config\ConfigException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Language\LanguageCode;
use MediaWiki\Page\PageReference;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleParser;
use TypeError;

/**
 * Service that abstracts retrieving configuration values, with methods to
 * transform between string values used in wikitext, IDs used in the database,
 * and labels used in the UI.
 *
 * @newable
 */
class WishlistConfig {

	public const ENABLED = 'CommunityRequestsEnable';
	public const HOMEPAGE = 'CommunityRequestsHomepage';
	public const WISH_CATEGORY = 'CommunityRequestsWishCategory';
	public const WISH_PAGE_PREFIX = 'CommunityRequestsWishPagePrefix';
	public const WISH_INDEX_PAGE = 'CommunityRequestsWishIndexPage';
	public const WISH_TEMPLATE = 'CommunityRequestsWishTemplate';
	public const WISH_TYPES = 'CommunityRequestsWishTypes';
	public const FOCUS_AREA_CATEGORY = 'CommunityRequestsFocusAreaCategory';
	public const FOCUS_AREA_PAGE_PREFIX = 'CommunityRequestsFocusAreaPagePrefix';
	public const FOCUS_AREA_INDEX_PAGE = 'CommunityRequestsFocusAreaIndexPage';
	public const FOCUS_AREA_TEMPLATE = 'CommunityRequestsFocusAreaTemplate';
	public const PROJECTS = 'CommunityRequestsProjects';
	public const STATUSES = 'CommunityRequestsStatuses';
	public const SUPPORT_TEMPLATE = 'CommunityRequestsSupportTemplate';
	public const VOTES_PAGE_SUFFIX = 'CommunityRequestsVotesPageSuffix';
	public const VOTE_TEMPLATE = 'CommunityRequestsVoteTemplate';
	public const WISH_VOTING_ENABLED = 'CommunityRequestsWishVotingEnabled';
	public const FOCUS_AREA_VOTING_ENABLED = 'CommunityRequestsFocusAreaVotingEnabled';
	public const CONSTRUCTOR_OPTIONS = [
		self::ENABLED,
		self::HOMEPAGE,
		self::WISH_CATEGORY,
		self::WISH_PAGE_PREFIX,
		self::WISH_INDEX_PAGE,
		self::WISH_TEMPLATE,
		self::WISH_TYPES,
		self::FOCUS_AREA_CATEGORY,
		self::FOCUS_AREA_PAGE_PREFIX,
		self::FOCUS_AREA_INDEX_PAGE,
		self::FOCUS_AREA_TEMPLATE,
		self::PROJECTS,
		self::STATUSES,
		self::SUPPORT_TEMPLATE,
		self::VOTES_PAGE_SUFFIX,
		self::VOTE_TEMPLATE,
		self::WISH_VOTING_ENABLED,
		self::FOCUS_AREA_VOTING_ENABLED,
	];

	private bool $enabled;
	private string $homepage;
	private string $wishCategory;
	private string $focusAreaCategory;
	private string $wishPagePrefix;
	private string $focusAreaPagePrefix;
	private string $wishIndexPage;
	private string $focusAreaIndexPage;
	private array $wishTemplate;
	private array $focusAreaTemplate;
	private array $voteTemplate;
	private array $wishTypes;
	private array $projects;
	private array $statuses;
	private string $supportTemplate;
	private string $votesPageSuffix;
	private bool $wishVotingEnabled;
	private bool $focusAreaVotingEnabled;

	public function __construct(
		ServiceOptions $config,
		private readonly TitleParser $titleParser,
		private readonly TitleFormatter $titleFormatter
	) {
		$this->enabled = $config->get( self::ENABLED );
		$this->homepage = $config->get( self::HOMEPAGE );
		$this->wishCategory = $config->get( self::WISH_CATEGORY );
		$this->wishPagePrefix = $config->get( self::WISH_PAGE_PREFIX );
		$this->wishIndexPage = $config->get( self::WISH_INDEX_PAGE );
		$this->wishTemplate = $config->get( self::WISH_TEMPLATE );
		$this->wishTypes = $config->get( self::WISH_TYPES );
		$this->focusAreaCategory = $config->get( self::FOCUS_AREA_CATEGORY );
		$this->focusAreaPagePrefix = $config->get( self::FOCUS_AREA_PAGE_PREFIX );
		$this->focusAreaIndexPage = $config->get( self::FOCUS_AREA_INDEX_PAGE );
		$this->focusAreaTemplate = $config->get( self::FOCUS_AREA_TEMPLATE );
		$this->projects = $config->get( self::PROJECTS );
		$this->statuses = $config->get( self::STATUSES );
		$this->supportTemplate = $config->get( self::SUPPORT_TEMPLATE );
		$this->votesPageSuffix = $config->get( self::VOTES_PAGE_SUFFIX );
		$this->voteTemplate = $config->get( self::VOTE_TEMPLATE );
		$this->wishVotingEnabled = $config->get( self::WISH_VOTING_ENABLED );
		$this->focusAreaVotingEnabled = $config->get( self::FOCUS_AREA_VOTING_ENABLED );
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

	public function getWishTemplateParams(): array {
		return $this->wishTemplate[ 'params' ];
	}

	public function getWishTypes(): array {
		return $this->wishTypes;
	}

	public function getFocusAreaCategory(): string {
		return $this->focusAreaCategory;
	}

	public function getFocusAreaIndexPage(): string {
		return $this->focusAreaIndexPage;
	}

	public function getFocusAreaTemplateParams(): array {
		return $this->focusAreaTemplate[ 'params' ];
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

	public function getSupportTemplate(): string {
		return $this->supportTemplate;
	}

	public function getVotesPageSuffix(): string {
		return $this->votesPageSuffix;
	}

	public function getVoteTemplateParams(): array {
		return $this->voteTemplate[ 'params' ];
	}

	public function isWishVotingEnabled(): bool {
		return $this->wishVotingEnabled;
	}

	public function isFocusAreaVotingEnabled(): bool {
		return $this->focusAreaVotingEnabled;
	}

	// Helpers

	/**
	 * Get the list of statuses that are eligible for voting.
	 *
	 * @return array Full config of statuses, keyed by wikitext value.
	 */
	public function getStatusesEligibleForVoting(): array {
		return array_filter(
			$this->statuses,
			static fn ( $status ) => $status[ 'voting' ] ?? true
		);
	}

	/**
	 * Get a list of status IDs that are eligible for voting.
	 *
	 * @return int[]
	 */
	public function getStatusIdsEligibleForVoting(): array {
		return array_values( array_map(
			static fn ( $status ) => (int)$status[ 'id' ],
			$this->getStatusesEligibleForVoting()
		) );
	}

	/**
	 * Get a list of wikitext values for statuses that are eligible for voting.
	 *
	 * @return string[]
	 */
	public function getStatusWikitextValsEligibleForVoting(): array {
		return array_keys( $this->getStatusesEligibleForVoting() );
	}

	/**
	 * Check if the given PageReference could be a wish page based on its title.
	 *
	 * @param ?PageReference $identity
	 * @return bool
	 */
	public function isWishPage( ?PageReference $identity ): bool {
		return $this->titleStartsWith( $identity, $this->wishPagePrefix );
	}

	/**
	 * Check if the given PageReference could be a focus area page based on its title.
	 *
	 * @param ?PageReference $identity
	 * @return bool
	 */
	public function isFocusAreaPage( ?PageReference $identity ): bool {
		return $this->titleStartsWith( $identity, $this->focusAreaPagePrefix );
	}

	/**
	 * Check if the given PageReference could be a wish or focus area page based on its title.
	 *
	 * @param ?PageReference $identity
	 * @return bool
	 */
	public function isWishOrFocusAreaPage( ?PageReference $identity ): bool {
		return $this->isWishPage( $identity ) || $this->isFocusAreaPage( $identity );
	}

	/**
	 * Check if the given PageReference could be a votes page based on its title.
	 *
	 * @param ?PageReference $identity
	 * @return bool
	 */
	public function isVotePage( ?PageReference $identity ): bool {
		if ( $identity === null ) {
			return false;
		}

		$identityStr = $this->titleFormatter->getPrefixedDBkey( $identity );
		$pageSuffix = $this->titleParser->parseTitle( $this->votesPageSuffix );
		$pageSuffixStr = $this->titleFormatter->getPrefixedDBkey( $pageSuffix );

		return str_ends_with( $identityStr, $pageSuffixStr );
	}

	private function titleStartsWith( ?PageReference $identity, string $prefix ): bool {
		if ( $identity === null ) {
			return false;
		}
		$pagePrefix = $this->titleParser->parseTitle( $prefix );

		$identityStr = $this->titleFormatter->getPrefixedDBkey( $identity );
		$pagePrefixStr = $this->titleFormatter->getPrefixedDBkey( $pagePrefix );
		$remaining = substr( $identityStr, strlen( $pagePrefixStr ) );

		$hasPrefix = str_starts_with( $identityStr, $pagePrefixStr );
		if ( $hasPrefix && is_numeric( $remaining ) ) {
			return true;
		} elseif ( !$hasPrefix ) {
			return false;
		}

		// Remove each numeric character from the beginning of $remaining
		$remaining = ltrim( $remaining, '0123456789' );

		// Remove leading slash.
		$remaining = ltrim( $remaining, '/' );

		// Check if the $remaining is probably a valid language code.
		return LanguageCode::isWellFormedLanguageTag( $remaining ) &&
			$remaining !== ltrim( $this->votesPageSuffix, '/' );
	}

	/**
	 * Get the wish or focus area display ID given a PageReference.
	 *
	 * @param ?PageReference $identity
	 * @return ?string The display ID, e.g. "W1" or "FA1".
	 */
	public function getEntityWikitextVal( ?PageReference $identity ): ?string {
		if ( !$identity || !$this->isWishOrFocusAreaPage( $identity ) ) {
			return null;
		}
		$fullPrefix = $this->isWishPage( $identity ) ? $this->wishPagePrefix : $this->focusAreaPagePrefix;
		$identityStr = $this->titleFormatter->getPrefixedDBkey( $identity );
		$slashPos = strrpos( $fullPrefix, '/' );
		$shortPrefix = $slashPos === false
			? $fullPrefix : substr( $fullPrefix, $slashPos + 1 );
		$remaining = substr( $identityStr, strlen( $fullPrefix ) );
		// Ignore subpages.
		$remaining = explode( '/', $remaining )[ 0 ];
		return $shortPrefix . preg_replace( '/^[^0-9]*/', '', $remaining );
	}

	/**
	 * Get a PageReference to a focus area page given the wikitext value.
	 *
	 * @param string $val The wikitext value, e.g. "FA1".
	 * @return ?PageReference The PageReference for the focus area, or null if not valid.
	 */
	public function getFocusAreaPageRefFromWikitextVal( string $val ): ?PageReference {
		try {
			$titleValue = $this->titleParser->parseTitle( $this->focusAreaPagePrefix .
				trim( preg_replace( '/[^0-9]/', '', $val ) ) );
		} catch ( MalformedTitleException | TypeError ) {
			return null;
		}
		return PageReferenceValue::localReference( $titleValue->getNamespace(), $titleValue->getDBkey() );
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
