<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Parser\CoreTagHooks;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Parser\PPNode;
use MediaWiki\Title\Title;
use MessageLocalizer;
use Psr\Log\LoggerInterface;
use RuntimeException;

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

	/** @var string This is overridden by subclasses to give the entity type */
	protected string $entityType = '';

	/** @var string[]|null Lazy-initialized key-value pairs from the parser */
	private ?array $args = null;

	/**
	 * @param WishlistConfig $config
	 * @param FocusAreaStore $focusAreaStore
	 * @param LoggerInterface $logger
	 * @param LinkRenderer $linkRenderer
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param (string|PPNode)[] $parts The parser function arguments in unexpanded form
	 */
	public function __construct(
		protected readonly WishlistConfig $config,
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
		return $this->parser->msg( $key, ...$params );
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

	protected function getDiv( string $field, string $text ): string {
		return Html::element(
			'div',
			[ 'class' => "ext-communityrequests-{$this->entityType}--$field" ],
			$text
		);
	}

	protected function getDivRaw( string $field, string $html ): string {
		return Html::rawElement(
			'div',
			[ 'class' => "ext-communityrequests-{$this->entityType}--$field" ],
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
		$cssClass = "cdx-info-chip ext-communityrequests-{$this->entityType}--status";

		$wikitextVal ??= $this->getArgs()[ AbstractWishlistEntity::PARAM_STATUS ] ?? '';

		$statusLabel = $this->config->getStatusLabelFromWikitextVal( $wikitextVal );
		if ( $statusLabel === null ) {
			$statusLabel = array_find(
				$this->config->getStatuses(),
				static fn ( $status ) => $status['default'] ?? false
			)['label'] ?? 'communityrequests-status-unknown';
			$this->parser->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
		}

		$style = $this->config->getStatuses()[$wikitextVal]['style'] ?? 'notice';
		$cssClass .= " cdx-info-chip--$style";

		return Html::rawElement(
			'span',
			[ 'class' => $cssClass ],
			Html::element(
				'span',
				[ 'class' => 'cdx-info-chip__text' ],
				// Messages that may be used here:
				// * communityrequests-status-under-review
				// * communityrequests-status-unsupported
				// * communityrequests-status-declined
				// * communityrequests-status-community-opportunity
				// * communityrequests-status-long-term-opportunity
				// * communityrequests-status-near-term-opportunity
				// * communityrequests-status-prioritized
				// * communityrequests-status-in-progress
				// * communityrequests-status-done
				$this->msg( $statusLabel )->text()
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
			[ 'class' => "ext-communityrequests-{$this->entityType}--$field" ],
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
			$this->msg( "communityrequests-{$this->entityType}-voting" )->text()
		);
		$out .= Html::openElement( 'div', [ 'class' => "ext-communityrequests-{$this->entityType}--voting" ] );

		// We need to wait for the full parser pass to complete to ensure all votes are counted.
		// Add a strip marker for the vote count to be added later in CommunityRequestsHooks::onParserAfterTidy().
		$out .= Html::openElement( 'p' );
		if ( !$this->isDefaultStatus() ) {
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
			$out .= $this->msg( "communityrequests-{$this->entityType}-voting-info-open" )->escaped();
		} elseif ( $this->isDefaultStatus() ) {
			$out .= $this->msg( "communityrequests-{$this->entityType}-voting-info-default" )->escaped();
		} else {
			$out .= $this->msg( "communityrequests-{$this->entityType}-voting-info-closed" )->escaped();
		}
		$out .= Html::closeElement( 'p' );

		$basePage = $this->config->getCanonicalEntityPageRef( $this->parser->getPage() );
		if ( $votingEnabled ) {
			// Container for the voting button added by JavaScript.
			$out .= Html::element( 'div', [ 'class' => 'ext-communityrequests-voting' ] );
			// Noscript fallback message.
			$out .= Html::rawElement( 'p', [],
				Html::element( 'noscript', [],
					$this->msg( 'communityrequests-voting-no-js' )->text()
				)
			);
			if ( $basePage ) {
				$this->parser->getOutput()->setJsConfigVar(
					'copyrightWarning',
					EditPage::getCopyrightWarning( $basePage, 'parse', $this )
				);
			}
		}

		// Transclude the /Votes subpage if it exists and the status is not the default status.
		if ( !$this->isDefaultStatus() && $basePage ) {
			$voteSubpagePath = Title::newFromPageReference( $basePage )->getPrefixedDBkey()
				. $this->config->getVotesPageSuffix();
			$voteSubpageTitle = Title::newFromText( $voteSubpagePath );
			if ( $voteSubpageTitle->exists() ) {
				$out .= $this->parser->recursiveTagParse( '{{:' . $voteSubpagePath . '}}' );
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
		return htmlspecialchars( $this->parser->getTargetLanguage()->timeanddate(
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
				// Not found -- just show plain text
				return htmlspecialchars( $focusAreaArg, ENT_NOQUOTES );
			}
		} else {
			return $this->msg( 'communityrequests-focus-area-unassigned' )->escaped();
		}
	}

	/**
	 * Add a tracking category with the specified message key.
	 * If Extension:Translate is installed, also add a category for the translation subpage.
	 *
	 * @param string $key The message key for the category name
	 */
	protected function addTrackingCategory( string $key ): void {
		$this->parser->addTrackingCategory( $key );
		$this->addTranslationCategory(
			$this->msg( $key )->inLanguage( $this->config->siteLanguage )->text()
		);
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
	 * @throws RuntimeException if the entity ID cannot be determined
	 */
	protected function setDisplayTitleAndIndicator(): void {
		$titleSpan = Html::element(
			'span',
			[ 'class' => "ext-communityrequests-{$this->entityType}--title" ],
			$this->getArg( AbstractWishlistEntity::PARAM_TITLE, '' )
		);
		$pageRef = $this->parser->getPage();
		if ( !$pageRef ) {
			throw new RuntimeException( 'Parser page is missing!' );
		}
		$entityPageStr = Title::newFromPageReference( $pageRef )->getPrefixedText();
		$entityIdSpan = Html::element(
			'span',
			[ 'class' => "ext-communityrequests-{$this->entityType}--id" ],
			$this->msg( 'parentheses', $entityPageStr )
		);
		$this->parser->getOutput()->setDisplayTitle(
			"$titleSpan $entityIdSpan"
		);
		CoreTagHooks::indicator(
			$this->getStatusChipHtml(),
			[ 'name' => "{$this->entityType}-status" ],
			$this->parser,
			$this->frame
		);
	}
}
