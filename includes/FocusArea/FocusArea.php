<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\FocusArea;

use InvalidArgumentException;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Utils\MWTimestamp;

/**
 * A value object representing a focus area in a particular language.
 */
class FocusArea extends AbstractWishlistEntity {

	// Constants used for parser function, extension data, and API parameters.
	public const PARAM_SHORT_DESCRIPTION = 'shortdescription';
	public const PARAM_OWNERS = 'owners';
	public const PARAM_VOLUNTEERS = 'volunteers';
	public const PARAMS = [
		self::PARAM_STATUS,
		self::PARAM_TITLE,
		self::PARAM_DESCRIPTION,
		self::PARAM_SHORT_DESCRIPTION,
		self::PARAM_OWNERS,
		self::PARAM_VOLUNTEERS,
		self::PARAM_CREATED,
		self::PARAM_BASE_LANG,
	];

	// Focus area properties.
	private string $shortdescription;
	private string $owners;
	private string $volunteers;

	/**
	 * @param PageIdentity $page The Title of the focus area page.
	 * @param string $lang The language (or translated language) of the focus area.
	 * @param array $fields The fields of the focus area, including:
	 *   - 'status' (int): The status ID of the focus area.
	 *   - 'title' (string): The title of the focus area.
	 *   - 'description' (?string): The description of the focus area.
	 *   - 'shortdescription' (string): The short description of the focus area.
	 *   - 'owners' (string): WMF owners of the focus area.
	 *   - 'volunteers' (string): Volunteers contributing to the focus area.
	 *   - 'votecount' (int): The number of votes for the focus area.
	 *   - 'created' (string): The creation timestamp of the focus area.
	 *   - 'updated' (string): The last updated timestamp of the focus area.
	 *   - 'status' (int): The status ID of the focus area.
	 *   - 'baselang' (string): The base language of the focus area.
	 * @throws InvalidArgumentException If the title or short description is empty.
	 */
	public function __construct(
		PageIdentity $page,
		string $lang,
		array $fields
	) {
		parent::__construct( $page, $lang, $fields );
		$this->shortdescription = $fields[self::PARAM_SHORT_DESCRIPTION] ?? '';
		$this->owners = $fields[self::PARAM_OWNERS] ?? '';
		$this->volunteers = $fields[self::PARAM_VOLUNTEERS] ?? '';
	}

	/**
	 * Get the focus area short description.
	 *
	 * @return string
	 */
	public function getShortDescription(): string {
		return $this->shortdescription;
	}

	/**
	 * Get the focus area owners.
	 *
	 * @return string
	 */
	public function getOwners(): string {
		return $this->owners;
	}

	/**
	 * Get the focus area volunteers.
	 *
	 * @return string
	 */
	public function getVolunteers(): string {
		return $this->volunteers;
	}

	/** @inheritDoc */
	public function toArray( WishlistConfig $config, bool $lowerCaseKeyNames = false ): array {
		return [
			self::PARAM_STATUS => $config->getStatusWikitextValFromId( $this->status ),
			self::PARAM_TITLE => $this->title,
			self::PARAM_DESCRIPTION => $this->description,
			self::PARAM_SHORT_DESCRIPTION => $this->shortdescription,
			self::PARAM_OWNERS => $this->owners,
			self::PARAM_VOLUNTEERS => $this->volunteers,
			self::PARAM_CREATED => $this->created,
			self::PARAM_BASE_LANG => $this->baselang,
			self::PARAM_VOTE_COUNT => $this->votecount,
		];
	}

	/** @inheritDoc */
	public function toWikitext( WishlistConfig $config ): WikitextContent {
		$wikitext = "{{#CommunityRequests: focus-area\n";
		foreach ( self::PARAMS as $param ) {
			// Match ID values to their wikitext representations, as defined by site configuration.
			$value = match ( $param ) {
				self::PARAM_STATUS => $config->getStatusWikitextValFromId( $this->status ),
				self::PARAM_CREATED => MWTimestamp::convert( TS_ISO_8601, $this->created ),
				default => $this->{ $param },
			};

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
		WishlistConfig $config
	): self {
		$fields = [
			self::PARAM_STATUS => $config->getStatusIdFromWikitextVal( $params[self::PARAM_STATUS] ?? '' ),
			self::PARAM_TITLE => $params[self::PARAM_TITLE] ?? '',
			self::PARAM_DESCRIPTION => $params[self::PARAM_DESCRIPTION] ?? null,
			self::PARAM_SHORT_DESCRIPTION => $params[self::PARAM_SHORT_DESCRIPTION] ?? '',
			self::PARAM_OWNERS => $params[self::PARAM_OWNERS] ?? '',
			self::PARAM_VOLUNTEERS => $params[self::PARAM_VOLUNTEERS] ?? '',
			self::PARAM_CREATED => $params[self::PARAM_CREATED] ?? null,
			self::PARAM_BASE_LANG => $lang,
			self::PARAM_VOTE_COUNT => $params[self::PARAM_VOTE_COUNT] ?? null,
		];
		return new self( $pageTitle, $lang, $fields );
	}
}
