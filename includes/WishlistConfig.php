<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Config\ConfigException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Language\LanguageNameUtils;
use MediaWiki\MainConfigNames;
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
	public const WISH_TYPES = 'CommunityRequestsWishTypes';
	public const FOCUS_AREA_CATEGORY = 'CommunityRequestsFocusAreaCategory';
	public const FOCUS_AREA_PAGE_PREFIX = 'CommunityRequestsFocusAreaPagePrefix';
	public const FOCUS_AREA_INDEX_PAGE = 'CommunityRequestsFocusAreaIndexPage';
	public const TAGS = 'CommunityRequestsTags';
	public const STATUSES = 'CommunityRequestsStatuses';
	public const VOTES_PAGE_SUFFIX = 'CommunityRequestsVotesPageSuffix';
	public const WISH_VOTING_ENABLED = 'CommunityRequestsWishVotingEnabled';
	public const FOCUS_AREA_VOTING_ENABLED = 'CommunityRequestsFocusAreaVotingEnabled';
	public const CONSTRUCTOR_OPTIONS = [
		self::ENABLED,
		self::HOMEPAGE,
		self::WISH_CATEGORY,
		self::WISH_PAGE_PREFIX,
		self::WISH_INDEX_PAGE,
		self::WISH_TYPES,
		self::FOCUS_AREA_CATEGORY,
		self::FOCUS_AREA_PAGE_PREFIX,
		self::FOCUS_AREA_INDEX_PAGE,
		self::TAGS,
		self::STATUSES,
		self::VOTES_PAGE_SUFFIX,
		self::WISH_VOTING_ENABLED,
		self::FOCUS_AREA_VOTING_ENABLED,
		MainConfigNames::LanguageCode,
	];

	private bool $enabled;
	private string $homepage;
	private string $wishCategory;
	private string $focusAreaCategory;
	private string $wishPagePrefix;
	private string $focusAreaPagePrefix;
	private string $wishIndexPage;
	private string $focusAreaIndexPage;
	private array $wishTypes;
	private array $navigationTags;
	private array $statuses;
	private string $votesPageSuffix;
	private bool $wishVotingEnabled;
	private bool $focusAreaVotingEnabled;
	public string $siteLanguage;

	public function __construct(
		ServiceOptions $config,
		private readonly TitleParser $titleParser,
		private readonly TitleFormatter $titleFormatter,
		private readonly LanguageNameUtils $languageNameUtils,
	) {
		$this->enabled = $config->get( self::ENABLED );
		$this->homepage = $config->get( self::HOMEPAGE );
		$this->wishCategory = $config->get( self::WISH_CATEGORY );
		$this->wishPagePrefix = $config->get( self::WISH_PAGE_PREFIX );
		$this->wishIndexPage = $config->get( self::WISH_INDEX_PAGE );
		$this->wishTypes = $config->get( self::WISH_TYPES );
		$this->focusAreaCategory = $config->get( self::FOCUS_AREA_CATEGORY );
		$this->focusAreaPagePrefix = $config->get( self::FOCUS_AREA_PAGE_PREFIX );
		$this->focusAreaIndexPage = $config->get( self::FOCUS_AREA_INDEX_PAGE );
		$this->navigationTags = $config->get( self::TAGS )['navigation'] ?? [];
		$this->statuses = $config->get( self::STATUSES );
		$this->votesPageSuffix = $config->get( self::VOTES_PAGE_SUFFIX );
		$this->wishVotingEnabled = $config->get( self::WISH_VOTING_ENABLED );
		$this->focusAreaVotingEnabled = $config->get( self::FOCUS_AREA_VOTING_ENABLED );
		$this->siteLanguage = $config->get( MainConfigNames::LanguageCode );
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

	public function getWishTypes(): array {
		return $this->wishTypes;
	}

	public function getFocusAreaCategory(): string {
		return $this->focusAreaCategory;
	}

	public function getFocusAreaIndexPage(): string {
		return $this->focusAreaIndexPage;
	}

	public function getFocusAreaPagePrefix(): string {
		return $this->focusAreaPagePrefix;
	}

	public function getNavigationTags(): array {
		return $this->navigationTags;
	}

	public function getStatuses(): array {
		return $this->statuses;
	}

	public function getVotesPageSuffix(): string {
		return $this->votesPageSuffix;
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
			static fn ( $status ) => $status['voting'] ?? true
		);
	}

	/**
	 * Get a list of status IDs that are eligible for voting.
	 *
	 * @return int[]
	 */
	public function getStatusIdsEligibleForVoting(): array {
		return array_values( array_map(
			static fn ( $status ) => (int)$status['id'],
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
	 * @param ?PageReference $reference
	 * @return bool
	 */
	public function isWishPage( ?PageReference $reference ): bool {
		return $this->isEntityPage( $reference, $this->wishPagePrefix );
	}

	/**
	 * Check if the given PageReference could be a focus area page based on its title.
	 *
	 * @param ?PageReference $reference
	 * @return bool
	 */
	public function isFocusAreaPage( ?PageReference $reference ): bool {
		return $this->isEntityPage( $reference, $this->focusAreaPagePrefix );
	}

	/**
	 * Check if the given PageReference could be a wish or focus area page based on its title.
	 *
	 * @param ?PageReference $reference
	 * @return bool
	 */
	public function isWishOrFocusAreaPage( ?PageReference $reference ): bool {
		return $this->isWishPage( $reference ) || $this->isFocusAreaPage( $reference );
	}

	private function isEntityPage( ?PageReference $reference, string $prefix ): bool {
		if ( $reference === null ) {
			return false;
		}
		$pagePrefix = $this->titleParser->parseTitle( $prefix );

		$referenceStr = $this->titleFormatter->getPrefixedDBkey( $reference );
		$pagePrefixStr = $this->titleFormatter->getPrefixedDBkey( $pagePrefix );
		$remaining = substr( $referenceStr, strlen( $pagePrefixStr ) );

		$hasPrefix = str_starts_with( $referenceStr, $pagePrefixStr );
		if ( $hasPrefix && is_numeric( $remaining ) ) {
			return true;
		} elseif ( !$hasPrefix ) {
			return false;
		}

		if ( preg_match( '/[0-9]/', $remaining ) !== 1 ) {
			return false;
		}

		// Remove each numeric character from the beginning of $remaining
		$remaining = ltrim( $remaining, '0123456789' );

		// Remove leading slash.
		$remaining = ltrim( $remaining, '/' );

		// Check if the $remaining is a valid language code.
		return $this->languageNameUtils->isKnownLanguageTag( $remaining );
	}

	/**
	 * Check if the given PageReference could be a votes page based on its title.
	 *
	 * @param ?PageReference $reference
	 * @return bool
	 */
	public function isVotesPage( ?PageReference $reference ): bool {
		if ( $reference === null ) {
			return false;
		}

		$referenceStr = $this->titleFormatter->getPrefixedDBkey( $reference );
		$pageSuffix = $this->titleParser->parseTitle( $this->votesPageSuffix );
		$pageSuffixStr = $this->titleFormatter->getPrefixedDBkey( $pageSuffix );

		if ( !str_ends_with( $referenceStr, $pageSuffixStr ) ) {
			return false;
		}

		$entityPageStr = substr( $referenceStr, 0, -strlen( $pageSuffixStr ) );
		$entityPageRef = PageReferenceValue::localReference( $reference->getNamespace(), $entityPageStr );
		$canonicalEntityPageRef = $this->getCanonicalEntityPageRef( $entityPageRef );
		return $canonicalEntityPageRef && $entityPageRef->isSamePageAs( $canonicalEntityPageRef );
	}

	/**
	 * Get the entity page reference from a votes page reference.
	 *
	 * @param ?PageReference $reference
	 * @return ?PageReference
	 */
	public function getEntityPageRefFromVotesPage( ?PageReference $reference ): ?PageReference {
		if ( $reference === null || !$this->isVotesPage( $reference ) ) {
			return null;
		}

		$referenceStr = $this->titleFormatter->getPrefixedDBkey( $reference );
		$votesPageSuffixStr = $this->titleFormatter->getPrefixedDBkey(
			$this->titleParser->parseTitle( $this->votesPageSuffix )
		);

		// Remove the votes page suffix.
		$entityPageStr = $this->titleParser->parseTitle(
			substr( $referenceStr, 0, -strlen( $votesPageSuffixStr ) )
		);
		return PageReferenceValue::localReference( $entityPageStr->getNamespace(), $entityPageStr->getDBkey() );
	}

	/**
	 * Get a page reference for the canonical entity (no language suffix)
	 * given an entity page or translation subpage.
	 *
	 * @param ?PageReference $reference
	 * @return ?PageReference null if the reference is null or not a valid translation subpage,
	 *   or the canonical page is not a wish or focus area page.
	 */
	public function getCanonicalEntityPageRef( ?PageReference $reference ): ?PageReference {
		if ( $reference === null ) {
			return null;
		}

		$parts = explode( '/', $reference->getDBkey() );
		$lastPart = end( $parts );
		if ( $this->languageNameUtils->isKnownLanguageTag( $lastPart ) ) {
			array_pop( $parts );
			$entityPageStr = implode( '/', $parts );
			try {
				$entityPage = $this->titleParser->parseTitle( $entityPageStr, $reference->getNamespace() );
			} catch ( MalformedTitleException ) {
				return null;
			}
			$reference = PageReferenceValue::localReference( $entityPage->getNamespace(), $entityPage->getDBkey() );
		}

		return $this->isWishOrFocusAreaPage( $reference ) ? $reference : null;
	}

	/**
	 * Check if the given PageReference is the wish index page.
	 *
	 * @param ?PageReference $reference
	 * @return bool
	 */
	public function isWishIndexPage( ?PageReference $reference ): bool {
		return $this->isIndexPage( $reference, $this->wishIndexPage );
	}

	/**
	 * Check if the given PageReference is the focus area index page.
	 *
	 * @param ?PageReference $reference
	 * @return bool
	 */
	public function isFocusAreaIndexPage( ?PageReference $reference ): bool {
		return $this->isIndexPage( $reference, $this->focusAreaIndexPage );
	}

	/**
	 * Check if the given PageReference is either the wish or focus area index page.
	 *
	 * @param ?PageReference $reference
	 * @return bool
	 */
	public function isWishOrFocusAreaIndexPage( ?PageReference $reference ): bool {
		return $this->isWishIndexPage( $reference ) || $this->isFocusAreaIndexPage( $reference );
	}

	private function isIndexPage( ?PageReference $reference, string $prefix ): bool {
		if ( $reference === null ) {
			return false;
		}
		$indexStr = $this->titleFormatter->getPrefixedDBkey(
			$this->titleParser->parseTitle( $prefix )
		);
		$referenceStr = $this->titleFormatter->getPrefixedDBkey( $reference );
		if ( $referenceStr === $indexStr ) {
			return true;
		}
		// Check if the reference starts with the index page, followed by a slash and a language code.
		if ( str_starts_with( $referenceStr, $indexStr ) ) {
			$remaining = substr( $referenceStr, strlen( $indexStr ) );
			// Remove leading slash.
			$remaining = ltrim( $remaining, '/' );
			// Check if the remaining part is a language code.
			return $this->languageNameUtils->isKnownLanguageTag( $remaining );
		}
		return false;
	}

	/**
	 * Get the wish or focus area display ID given a PageReference.
	 *
	 * @param ?PageReference $reference
	 * @return ?string The display ID, e.g. "W1" or "FA1".
	 */
	public function getEntityWikitextVal( ?PageReference $reference ): ?string {
		if ( !$reference || !$this->isWishOrFocusAreaPage( $reference ) ) {
			return null;
		}
		$fullPrefix = $this->isWishPage( $reference ) ? $this->wishPagePrefix : $this->focusAreaPagePrefix;
		$referenceStr = $this->titleFormatter->getPrefixedDBkey( $reference );
		$slashPos = strrpos( $fullPrefix, '/' );
		$shortPrefix = $slashPos === false
			? $fullPrefix : substr( $fullPrefix, $slashPos + 1 );
		$remaining = substr( $referenceStr, strlen( $fullPrefix ) );
		// Ignore subpages.
		$remaining = explode( '/', $remaining )[0];
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
		return $this->wishTypes[$type]['label'] ?? null;
	}

	/**
	 * Get the ID of a tag given its wikitext value.
	 *
	 * @param string $tag
	 * @return ?int The ID of the tag or null if not found.
	 */
	public function getTagIdFromWikitextVal( string $tag ): ?int {
		$tag = trim( $tag );
		if ( isset( $this->navigationTags[$tag] ) ) {
			return (int)$this->navigationTags[$tag]['id'];
		}
		return null;
	}

	/**
	 * Get the label of a tag from its wikitext value.
	 *
	 * @param string $tag
	 * @return ?string The message key for the tag, or null if not found.
	 */
	public function getTagLabelFromWikitextVal( string $tag ): ?string {
		$tag = trim( $tag );
		return isset( $this->navigationTags[$tag] ) ?
			$this->navigationTags[$tag]['label'] ?? "communityrequests-tag-$tag" :
			null;
	}

	/**
	 * Get the ID of a status from its wikitext value.
	 *
	 * @param string $status
	 * @return ?int The ID of the status, or null if not found.
	 */
	public function getStatusIdFromWikitextVal( string $status ): ?int {
		$status = trim( $status );
		return isset( $this->statuses[$status] ) ?
			$this->getIdFromWikitextVal( $status, $this->statuses ) :
			null;
	}

	/**
	 * Get the label of a status from its wikitext value.
	 *
	 * @param string $status
	 * @return ?string The label of the status, or null if not found.
	 */
	public function getStatusLabelFromWikitextVal( string $status ): ?string {
		$status = trim( $status );
		return $this->statuses[$status]['label'] ?? null;
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
		if ( isset( $config[$val] ) ) {
			return (int)$config[$val]['id'];
		}
		// If the value is not found, return the default value.
		foreach ( $config as $item ) {
			if ( $item['default'] ?? false ) {
				return (int)$item['id'];
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
	 * Get the wikitext value from a tag ID.
	 *
	 * @param int $id
	 * @return string
	 * @throws ConfigException If the ID is not found in the configuration.
	 */
	public function getTagWikitextValFromId( int $id ): string {
		return $this->getWikitextValFromId( $id, $this->navigationTags );
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
			if ( (int)$item['id'] === $id ) {
				return $key;
			}
		}
		throw new ConfigException( "ID '$id' not found in configuration." );
	}
}
