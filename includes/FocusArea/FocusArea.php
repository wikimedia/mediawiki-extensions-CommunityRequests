<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\FocusArea;

use InvalidArgumentException;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\TitleValue;
use MediaWiki\Utils\MWTimestamp;

/**
 * A value object representing a focus area in a particular language.
 */
class FocusArea extends AbstractWishlistEntity {

	// Constants used for parsing and constructing the template invocation.
	public const TAG_ATTR_SHORT_DESCRIPTION = 'shortdescription';
	public const TAG_ATTR_OWNERS = 'owners';
	public const TAG_ATTR_VOLUNTEERS = 'volunteers';
	public const TAG_ATTRS = [
		self::TAG_ATTR_STATUS,
		self::TAG_ATTR_TITLE,
		self::TAG_ATTR_DESCRIPTION,
		self::TAG_ATTR_SHORT_DESCRIPTION,
		self::TAG_ATTR_OWNERS,
		self::TAG_ATTR_VOLUNTEERS,
		self::TAG_ATTR_CREATED,
		self::TAG_ATTR_BASE_LANG,
	];

	// Focus area properties.
	private string $shortDescription;
	private string $owners;
	private string $volunteers;

	/**
	 * @param PageIdentity $page The Title of the focus area page.
	 * @param string $lang The language (or translated language) of the focus area.
	 * @param array $fields The fields of the focus area, including:
	 *   - 'status' (int): The status ID of the focus area.
	 *   - 'title' (string): The title of the focus area.
	 *   - 'description' (?string): The description of the focus area.
	 *   - 'shortDescription' (string): The short description of the focus area.
	 *   - 'owners' (string): WMF owners of the focus area.
	 *   - 'volunteers' (string): Volunteers contributing to the focus area.
	 *   - 'voteCount' (int): The number of votes for the focus area.
	 *   - 'created' (string): The creation timestamp of the focus area.
	 *   - 'updated' (string): The last updated timestamp of the focus area.
	 *   - 'status' (int): The status ID of the focus area.
	 *   - 'baseLang' (string): The base language of the focus area.
	 * @throws InvalidArgumentException If the title or short description is empty.
	 */
	public function __construct(
		PageIdentity $page,
		string $lang,
		array $fields
	) {
		parent::__construct( $page, $lang, $fields );
		$this->shortDescription = $fields['shortDescription'] ?? '';
		$this->owners = $fields['owners'] ?? '';
		$this->volunteers = $fields['volunteers'] ?? '';
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
		$ret = [
			'status' => $config->getStatusWikitextValFromId( $this->status ),
			'title' => $this->title,
			'description' => $this->description,
			'shortDescription' => $this->shortDescription,
			'owners' => $this->owners,
			'volunteers' => $this->volunteers,
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
			':' . $config->getFocusAreaTemplatePage();
		$wikitext = "{{" . $templateCall . "\n";
		foreach ( self::TAG_ATTRS as $attr ) {
			$param = $config->getFocusAreaTemplateParams()[ $attr ];
			// Match ID values to their wikitext representations, as defined by site configuration.
			$value = match ( $attr ) {
				self::TAG_ATTR_STATUS => $config->getStatusWikitextValFromId( $this->status ),
				self::TAG_ATTR_SHORT_DESCRIPTION => $this->shortDescription,
				self::TAG_ATTR_CREATED => MWTimestamp::convert( TS_ISO_8601, $this->created ),
				self::TAG_ATTR_BASE_LANG => $this->baseLang,
				default => $this->{ $attr },
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
			'status' => $config->getStatusIdFromWikitextVal( $params[ self::TAG_ATTR_STATUS ] ?? '' ),
			'title' => $params[ self::TAG_ATTR_TITLE ] ?? '',
			'description' => $params[ self::TAG_ATTR_DESCRIPTION ] ?? null,
			'shortDescription' => $params[ self::TAG_ATTR_SHORT_DESCRIPTION ] ?? '',
			'owners' => $params[ self::TAG_ATTR_OWNERS ] ?? '',
			'volunteers' => $params[ self::TAG_ATTR_VOLUNTEERS ] ?? '',
			'created' => $params[ self::TAG_ATTR_CREATED ] ?? null,
			'baseLang' => $lang,
		];
		return new self( $pageTitle, $lang, $fields );
	}
}
