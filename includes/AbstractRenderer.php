<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Parser\CoreTagHooks;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Parser\PPNode;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MessageLocalizer;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\Message\MessageValue;

/**
 * The base class for wish/focus area parser function implementations
 */
abstract class AbstractRenderer implements MessageLocalizer {
	public const TRACKING_CATEGORY = 'communityrequests-category';
	public const ERROR_TRACKING_CATEGORY = 'communityrequests-error-category';
	public const EXT_DATA_KEY = 'CommunityRequests-ext-data';
	public const VOTING_STRIP_MARKER = Parser::MARKER_PREFIX . "-communityrequests-voting-" . Parser::MARKER_SUFFIX;
	// This fragment is also in modules/voting/Button.vue
	public const LINK_FRAGMENT_VOTING = 'Voting';
	public const LINK_FRAGMENT_WISHES = 'Wishes';

	/** @var string This is overridden by subclasses to give the renderer type. It should match what's used in RendererFactory. */
	protected string $rendererType = '';

	/** @var string[]|null Lazy-initialized key-value pairs from the parser */
	private ?array $args = null;

	/**
	 * @param WishlistConfig $config
	 * @param WishStore $wishStore
	 * @param FocusAreaStore $focusAreaStore
	 * @param LoggerInterface $logger
	 * @param LinkRenderer $linkRenderer
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param (string|PPNode)[] $parts The parser function arguments in unexpanded form
	 */
	public function __construct(
		protected readonly WishlistConfig $config,
		protected readonly WishStore $wishStore,
		protected readonly FocusAreaStore $focusAreaStore,
		protected readonly LoggerInterface $logger,
		protected readonly LinkRenderer $linkRenderer,
		protected readonly Parser $parser,
		protected readonly PPFrame $frame,
		protected array $parts
	) {
	}

	/**
	 * Render the parser function
	 *
	 * @return string HTML
	 */
	abstract public function render(): string;

	/** @inheritDoc */
	public function msg( $key, ...$params ) {
		return $this->parser->msg( $key, ...$params )
			->inLanguage( $this->parser->getOptions()->getUserLangObj() );
	}

	/**
	 * Get a parser strip marker for a focus area card's wish count message.
	 *
	 * @param int $pageId
	 * @return string
	 */
	public static function getWishCountStripMarker( int $pageId ): string {
		return Parser::MARKER_PREFIX . "-communityrequests-wishcount-$pageId-" . Parser::MARKER_SUFFIX;
	}

	/**
	 * Get an error message to display, add it to the parser warnings, and add the page to the error tracking category.
	 *
	 * @param string $msg The message name.
	 * @param array|string $params The message parameter (if not an array) or parameters.
	 * @param string $element The HTML element with which to wrap the error message.
	 * @param bool $track Whether to add the page to the error tracking category and add a parser warning.
	 * @param bool $raw Whether to parse the message as raw wikitext (no HTML-escaping).
	 *
	 * @return string The error HTML.
	 */
	public function getErrorMessage(
		string $msg,
		array|string $params,
		string $element = 'span',
		bool $track = true,
		bool $raw = false,
	): string {
		if ( $track ) {
			$this->parser->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
			if ( !is_array( $params ) ) {
				$params = [ $params ];
			}
			$this->parser->getOutput()->addWarningMsgVal( MessageValue::new( $msg, $params ) );
		}
		if ( $raw ) {
			return Html::rawElement(
				$element,
				[ 'class' => 'error' ],
				$this->msg( $msg, $params )->parse()
			);
		}
		return Html::element( $element, [ 'class' => 'error' ], $this->msg( $msg, $params )->text() );
	}

	/**
	 * Get a raw error message to display, without HTML-escaping.
	 *
	 * @see self::getErrorMessage()
	 * @param string $msg
	 * @param array|string $params
	 * @param string $element
	 * @param bool $track
	 *
	 * @return string The error HTML.
	 */
	public function getErrorMessageRaw(
		string $msg, array|string $params, string $element = 'span', bool $track = true
	): string {
		return $this->getErrorMessage( $msg, $params, $element, $track, true );
	}

	/**
	 * Get the associative array of parser function arguments
	 * indexed by the names given in the source.
	 *
	 * @return (string|int)[]
	 */
	protected function getUnmappedArgs(): array {
		if ( $this->args === null ) {
			$parts = $this->parts;
			array_shift( $parts );
			$childFrame = $this->frame->newChild( $parts );
			$this->args = $childFrame->getArguments();
		}
		return $this->args;
	}

	/**
	 * Get the parser function arguments keyed by their names.
	 *
	 * @return array
	 */
	protected function getArgs(): array {
		$mappedArgs = [];
		foreach ( $this->getUnmappedArgs() as $name => $value ) {
			$name = (string)$name;
			$mappedArgs[$name] = $value;
		}
		return $mappedArgs;
	}

	// HTML helpers for rendering wish or focus area pages.

	/**
	 * Generate a div element for the specified field and text.
	 * This is used for sections of entity pages, giving them a unique CSS class.
	 *
	 * @param string $field
	 * @param string $text
	 * @return string Safe HTML
	 */
	protected function getDiv( string $field, string $text ): string {
		return Html::element(
			'div',
			[ 'class' => "ext-communityrequests-{$this->rendererType}--$field" ],
			$text
		);
	}

	/**
	 * Same as ::getDiv() but for raw HTML content.
	 * This is usually used for displaying user-submitted content on entity pages.
	 * As such, it accepts an optional $lang parameter to set the lang/dir attributes
	 * accordingly so the nodes are translatable by the ext.communityrequests.mint module.
	 *
	 * @param string $field
	 * @param string $html
	 * @param ?Language $lang
	 * @return string Raw HTML
	 */
	protected function getDivRaw( string $field, string $html, ?Language $lang = null ): string {
		return Html::rawElement(
			'div',
			[
				'class' => "ext-communityrequests-{$this->rendererType}--$field",
				'lang' => $lang?->getCode(),
				'dir' => $lang?->getDir(),
			],
			"\n$html\n"
		);
	}

	/**
	 * Get status chip HTML for the current entity type.
	 *
	 * @param ?string $wikitextVal If not provided, will use the status from $this->getArgs().
	 * @return string HTML for status chip
	 */
	protected function getStatusChipHtml( ?string $wikitextVal = null ): string {
		$cssClass = "cdx-info-chip ext-communityrequests-{$this->rendererType}--status";

		$wikitextVal ??= $this->getArgs()[ AbstractWishlistEntity::PARAM_STATUS ] ?? '';

		$statusEntityType = $this->rendererType === 'wish' ? 'wish' : 'focus-area';
		$statusMsg = $this->config->getStatusLabelFromWikitextVal( $statusEntityType, $wikitextVal );
		$error = '';
		if ( $statusMsg === null ) {
			$defaultStatus = $this->config->getDefaultStatusWikitextVal();
			$statusMsg = "communityrequests-status-{$this->rendererType}-{$defaultStatus}";
			$error = $this->getErrorMessage( 'communityrequests-error-invalid-status', $wikitextVal );
		}

		$style = $this->config->getStatuses()[$wikitextVal]['style'] ?? 'notice';
		$cssClass .= " cdx-info-chip--$style";

		return $error . Html::rawElement(
			'span',
			[ 'class' => $cssClass ],
			Html::element(
				'span',
				[ 'class' => 'cdx-info-chip__text' ],
				// Messages that may be used here:
				// * communityrequests-status-wish-under-review
				// * communityrequests-status-wish-declined
				// * communityrequests-status-wish-community-opportunity
				// * communityrequests-status-wish-long-term-opportunity
				// * communityrequests-status-wish-near-term-opportunity
				// * communityrequests-status-wish-prioritized
				// * communityrequests-status-wish-in-progress
				// * communityrequests-status-wish-done
				// * communityrequests-status-focus-area-under-review
				// * communityrequests-status-focus-area-declined
				// * communityrequests-status-focus-area-community-opportunity
				// * communityrequests-status-focus-area-long-term-opportunity
				// * communityrequests-status-focus-area-near-term-opportunity
				// * communityrequests-status-focus-area-prioritized
				// * communityrequests-status-focus-area-in-progress
				// * communityrequests-status-focus-area-done
				$this->msg( $statusMsg )->text()
			)
		);
	}

	/**
	 * Generate a list item element for the specified field and parameter.
	 *
	 * @param string $field
	 * @param string $param
	 * @return string HTML list item element
	 */
	protected function getListItem( string $field, string $param ): string {
		return Html::rawElement(
			'li',
			[ 'class' => "ext-communityrequests-{$this->rendererType}--$field" ],
			// Messages used here include:
			// * communityrequests-wish-created
			// * communityrequests-wish-updated
			// * communityrequests-wish-proposer
			$this->msg( "communityrequests-wish-$field", $param )->parse()
		);
	}

	/**
	 * Get the HTML for the voting section of a wish or focus area page.
	 *
	 * @param bool $votingEnabled Whether voting is enabled
	 * @return string HTML
	 */
	protected function getVotingSection( bool $votingEnabled ): string {
		// Make sure the status allows voting.
		$votingEnabled = $votingEnabled && in_array(
			$this->getArg( AbstractWishlistEntity::PARAM_STATUS, '' ),
			$this->config->getStatusWikitextValsEligibleForVoting()
		);

		$out = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading2', 'id' => self::LINK_FRAGMENT_VOTING ],
			// Messages used here:
			// * communityrequests-focus-area-voting
			// * communityrequests-wish-voting
			$this->msg( "communityrequests-{$this->rendererType}-voting" )->text()
		);
		$out .= Html::openElement( 'div', [
			'class' => "ext-communityrequests-{$this->rendererType}--voting mw-notalk"
		] );

		// We need to wait for the full parser pass to complete to ensure all votes are counted.
		// Add a strip marker for the vote count to be added later in CommunityRequestsHooks::onParserAfterTidy().
		$out .= Html::openElement( 'p' );
		if ( !$this->isDefaultStatus() || $votingEnabled ) {
			$out .= self::VOTING_STRIP_MARKER . ' ';
		}
		// Messages used in the following block:
		// * communityrequests-focus-area-voting-info-open
		// * communityrequests-focus-area-voting-info-default
		// * communityrequests-focus-area-voting-info-closed
		// * communityrequests-wish-voting-info-open
		// * communityrequests-wish-voting-info-default
		// * communityrequests-wish-voting-info-closed
		if ( $votingEnabled ) {
			$out .= $this->msg( "communityrequests-{$this->rendererType}-voting-info-open" )->escaped();
		} elseif ( $this->isDefaultStatus() ) {
			$out .= $this->msg( "communityrequests-{$this->rendererType}-voting-info-default" )->escaped();
		} else {
			$out .= $this->msg( "communityrequests-{$this->rendererType}-voting-info-closed" )->escaped();
		}
		$out .= Html::closeElement( 'p' );

		$basePage = $this->config->getCanonicalEntityPageRef( $this->parser->getPage() );
		if ( $votingEnabled ) {
			// Container for the voting button added by JavaScript.
			$out .= Html::element( 'div', [ 'class' => 'ext-communityrequests-voting' ] );
			// Noscript fallback message.
			$out .= Html::rawElement( 'noscript', [],
				$this->getErrorMessage( 'communityrequests-voting-no-js', [], 'p', false )
			);
			if ( $basePage ) {
				$this->parser->getOutput()->setJsConfigVar(
					'copyrightWarning',
					EditPage::getCopyrightWarning( $basePage, 'parse', $this )
				);
			}
		}

		// Transclude the /Votes subpage if it exists and the status is not the default status,
		// or if voting is enabled.
		if ( $basePage && ( $votingEnabled || !$this->isDefaultStatus() ) ) {
			$voteSubpagePath = Title::newFromPageReference( $basePage )->getPrefixedDBkey()
				. $this->config->getVotesPageSuffix();
			$voteSubpageTitle = Title::newFromText( $voteSubpagePath );
			if ( $voteSubpageTitle->exists() ) {
				$voteSubpageContent = $this->parser->recursiveTagParse( '{{:' . $voteSubpagePath . '}}' );
				if ( $voteSubpageContent ) {

					$out .= Html::element(
						'div',
						[ 'class' => 'mw-heading mw-heading3' ],
						$this->msg( "communityrequests-{$this->rendererType}-voters-heading" )->text()
					);
				}
				$out .= $voteSubpageContent;
				// Add template dependency to ensure the votes subpage is kept up to date.
				$this->parser->getOutput()->addTemplate(
					$voteSubpageTitle,
					$voteSubpageTitle->getId(),
					$voteSubpageTitle->getLatestRevID()
				);
			} else {
				// Make sure the entity page is updated when the votes subpage is created.
				$this->parser->getOutput()->addTemplate( $voteSubpageTitle, 0, 0 );
			}
		}

		// Close the voting container.
		$out .= Html::closeElement( 'div' );

		return $out;
	}

	/**
	 * Format a date for display in the target language.
	 *
	 * @param string $date The date to format, in ISO 8601 format
	 * @return string
	 */
	protected function formatDate( string $date ): string {
		return htmlspecialchars( $this->parser->getOptions()->getUserLangObj()->timeanddate(
			$date,
			true,
			$this->parser->getOptions()->getDateFormat()
		) );
	}

	/**
	 * Validates the arguments against the required fields.
	 *
	 * @param array $args
	 * @param string[] $requiredFields
	 * @return string[]
	 */
	protected function validateArguments( array $args, array $requiredFields ): array {
		$missingFields = array_filter(
			$requiredFields,
			static fn ( string $field ) => !( $args[$field] ?? '' )
		);

		if ( $missingFields ) {
			// Add tracking category for missing critical data.
			$this->parser->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
		}

		return $missingFields;
	}

	/**
	 * Get a template argument with fallback handling.
	 *
	 * @param string $key
	 * @param string|int $default The default value if the argument is missing or empty
	 * @return string Trimmed value or fallback (not HTML-escaped)
	 */
	protected function getArg( string $key, string|int $default ): string {
		$value = (string)( $this->getArgs()[$key] ?? $default );
		if ( $value == $default ) {
			return $value;
		}

		return trim( $value ) ?: (string)$default;
	}

	/**
	 * Get an HTML-safe template argument with fallback handling.
	 * Same as getArg() but HTML-escapes the result for safe output.
	 *
	 * @param string $key
	 * @param string|int $default
	 * @return string HTML-safe value
	 */
	protected function getSafeArg( string $key, string|int $default ): string {
		return htmlspecialchars( $this->getArg( $key, $default ) );
	}

	/**
	 * Returns an error message for missing fields.
	 *
	 * @param string[] $missingFields
	 * @return string HTML
	 */
	protected function getMissingFieldsErrorMessage( array $missingFields ): string {
		return Html::element(
			'p',
			[ 'class' => 'error' ],
			$this->msg(
				'communityrequests-error-required-fields',
				$this->parser->getTargetLanguage()->commaList( $missingFields ),
				count( $missingFields )
			)->text()
		);
	}

	/**
	 * Get a link to the specified focus area or a suitable placeholder
	 *
	 * @param string|null $focusAreaArg The focus area input parameter
	 * @param string|null $label The label for the link parameter
	 * @return string HTML
	 */
	protected function getFocusAreaLink( ?string $focusAreaArg, ?string $label = null ): string {
		if ( $focusAreaArg ) {
			$id = $this->focusAreaStore->getIdFromInput( $focusAreaArg );
			$pageIdentity = Title::newFromText(
				$this->focusAreaStore->getPagePrefix() . $id
			);
			$entity = $this->focusAreaStore->get(
				$pageIdentity,
				$this->parser->getContentLanguage()->getCode()
			);
			if ( $entity ) {
				return $this->linkRenderer->makeKnownLink(
					$pageIdentity,
					$label ?: $entity->getTitle()
				);
			} else {
				// Not found, show unassigned
				return $this->msg( 'communityrequests-focus-area-unassigned' )->escaped();
			}
		} else {
			return $this->msg( 'communityrequests-focus-area-unassigned' )->escaped();
		}
	}

	/**
	 * Get the wikitext to output at the top of an entity page (the languages list, and link to the index page).
	 *
	 * @return string
	 */
	protected function getEntityTopSection() {
		$languageLinks = ExtensionRegistry::getInstance()->isLoaded( 'Translate' ) ?
			$this->parser->recursiveTagParse( '<languages/>' ) : '';
		$backLink = '';
		if ( $this->config->isWishPage( $this->parser->getPage() ) ) {
			$backLink .= $this->linkRenderer->makeKnownLink(
				Title::newFromText( $this->config->getWishIndexPage() ),
				$this->msg( 'communityrequests-view-all-wishes' )->text()
			);
		} else {
			$backLink .= $this->linkRenderer->makeKnownLink(
				Title::newFromText( $this->config->getFocusAreaIndexPage() ),
				$this->msg( 'communityrequests-view-all-focus-areas' )->text()
			);
		}
		return $languageLinks . $backLink;
	}

	/**
	 * Add a tracking category with the specified message key.
	 * If Extension:Translate is installed and this is a translation subpage,
	 * use a category for the translation subpage instead.
	 *
	 * @param string $key The message key for the category name
	 */
	protected function addTrackingCategory( string $key ): void {
		$parserPage = $this->parser->getPage();
		$isBasePage = $parserPage !== null &&
			$this->config->getCanonicalEntityPageRef( $parserPage )->isSamePageAs( $parserPage );
		if ( $isBasePage ) {
			$this->parser->addTrackingCategory( $key );
		} else {
			$this->addTranslationCategory(
				$this->msg( $key )->inLanguage( $this->config->siteLanguage )->text()
			);
		}
	}

	/**
	 * If Extension:Translate is installed, we want to add categories for
	 * translation subpages per convention; see https://w.wiki/FDXd
	 *
	 * @param string $category
	 */
	protected function addTranslationCategory( string $category ): void {
		if ( in_array( 'translation', $this->parser->getFunctionHooks() ) ) {
			$translationCategory = $category . $this->parser->internalParse( '{{#translation:}}' );
			$this->parser->getOutput()->addCategory( $translationCategory );
		}
	}

	/**
	 * Check if the current entity status matches the default status from configuration
	 *
	 * @return bool
	 */
	private function isDefaultStatus(): bool {
		$status = $this->getArg( AbstractWishlistEntity::PARAM_STATUS, '' );
		return $this->config->getStatuses()[$status]['default'] ?? false;
	}

	/**
	 * Set the display title to be the entity title, and add the status chip as an indicator.
	 *
	 * @throws RuntimeException if the parser page is missing
	 */
	protected function setDisplayTitleAndIndicator(): void {
		$titleSpan = $this->getTitleSpan();
		$pageRef = $this->parser->getPage();
		if ( !$pageRef ) {
			throw new RuntimeException( 'Parser page is missing!' );
		}
		$entityPageStr = Title::newFromPageReference( $pageRef )->getPrefixedText();
		$entityIdSpan = Html::element(
			'span',
			[ 'class' => "ext-communityrequests-{$this->rendererType}--id" ],
			$this->msg( 'parentheses', $entityPageStr )
		);
		$this->parser->getOutput()->setDisplayTitle(
			"$titleSpan $entityIdSpan"
		);
		CoreTagHooks::indicator(
			$this->getStatusChipHtml(),
			[ 'name' => "{$this->rendererType}-status" ],
			$this->parser,
			$this->frame
		);
	}

	private function getTitleSpan(): string {
		// Previously saved titles will have wikitext syntax escaped.
		$titleHtml = Sanitizer::decodeCharReferences(
			$this->getArg( AbstractWishlistEntity::PARAM_TITLE, '' )
		);

		// Template syntax and parser tags are supposed to be pre-escaped by the API,
		// but users with the 'manually-edit-wishlist' right may forget to escape wikitext.
		// If we see strip markers, use a best-effort title and put the page in the error tracking category.
		if ( str_contains( $titleHtml, Parser::MARKER_PREFIX ) ) {
			$titleHtml = $this->parser->killMarkers( $titleHtml );
			$this->parser->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
		}

		// Extract the 'lang', 'dir' and 'class' attributes from the title HTML if present,
		// which will be re-applied to the title span below (needed by Extension:Translate).
		[ $langAttr, $dirAttr, $classAttr ] = $this->getAttrsFromTitleHtml( $titleHtml );

		// If there are attributes we want to preserve, remove the outer <span> so we can re-apply them safely.
		// Any other <span> will be considered part of the title itself and is preserved.
		if ( $langAttr || $dirAttr || $classAttr ) {
			$spanRegex = '/^<span\b[^>]*>(.*?)<\/span>$/i';
			$titleHtml = preg_replace( $spanRegex, '$1', $titleHtml ) ?: $titleHtml;
		}

		// Update extension data with the $titleHtml for later storage.
		$extData = $this->parser->getOutput()->getExtensionData( self::EXT_DATA_KEY );
		$extData[AbstractWishlistEntity::PARAM_TITLE] = $titleHtml;
		$this->parser->getOutput()->setExtensionData( self::EXT_DATA_KEY, $extData );

		return Html::element(
			'span',
			[
				'class' => [
					"ext-communityrequests-{$this->rendererType}--title",
					$classAttr,
				],
				// Set lang/dir to match the target language if it wasn't already specified.
				'lang' => $langAttr ?? $this->parser->getTargetLanguage()->getCode(),
				'dir' => $dirAttr ?? $this->parser->getTargetLanguage()->getDir(),
			],
			$titleHtml
		);
	}

	private function getAttrsFromTitleHtml( string $titleHtml ): array {
		$xmlParser = xml_parser_create( 'UTF-8' );
		xml_parse_into_struct( $xmlParser, $titleHtml, $values );
		$ret = [ null, null, null ];
		if ( ( $values[0]['tag'] ?? null ) === 'SPAN' ) {
			$ret = [
				$values[0]['attributes']['LANG'] ?? null,
				$values[0]['attributes']['DIR'] ?? null,
				$values[0]['attributes']['CLASS'] ?? null,
			];
		}
		return $ret;
	}
}
