<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Message\Message;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Parser\PPNode;
use MediaWiki\Title\Title;
use Psr\Log\LoggerInterface;

/**
 * The base class for wish/focus area parser function implementations
 */
abstract class AbstractRenderer {
	public const TRACKING_CATEGORY = 'communityrequests-category';
	public const ERROR_TRACKING_CATEGORY = 'communityrequests-error-category';
	public const EXT_DATA_KEY = 'CommunityRequests-ext-data';
	public const VOTING_STRIP_MARKER = Parser::MARKER_PREFIX . "-communityrequests-voting-" . Parser::MARKER_SUFFIX;

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

	/**
	 * Message proxy
	 *
	 * @param string $msg
	 * @param mixed ...$params
	 * @return Message
	 */
	protected function msg( string $msg, ...$params ): Message {
		return $this->parser->msg( $msg, ...$params );
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
			'p',
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
	 * @return string HTML for status chip
	 */
	protected function getStatusChipHtml(): string {
		$cssClass = 'cdx-info-chip ext-communityrequests-' . $this->entityType . '--status';

		$statusValue = $this->getArgs()[AbstractWishlistEntity::PARAM_STATUS] ?? '';

		if ( $statusValue === 'done' ) {
			$cssClass .= ' cdx-info-chip--success';
		}

		$statusLabel = $this->config->getStatusLabelFromWikitextVal( $statusValue );
		if ( $statusLabel === null ) {
			$statusLabel = 'communityrequests-status-unknown';
			$this->parser->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
		}

		return Html::rawElement(
			'span',
			[ 'class' => $cssClass ],
			Html::element(
				'span',
				[ 'class' => 'cdx-info-chip__text' ],
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

	protected function getFakeButton( Title $title, string $msgKey, string $icon ): string {
		return Html::rawElement(
			'a',
			[
				'href' => $title->getLocalURL(),
				'class' => [
					'cdx-button',
					'cdx-button--fake-button',
					'cdx-button--fake-button--enabled',
					'cdx-button--action-default',
					'cdx-button--weight-normal',
					'cdx-button--enabled'
				],
				'role' => 'button',
			],
			Html::element(
				'span',
				[
					'class' => [ 'cdx-button__icon', "ext-communityrequests-{$this->entityType}--$icon" ],
					'aria-hidden' => 'true',
				],
			) . $this->msg( $msgKey )->escaped()
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
			[ 'class' => 'mw-heading mw-heading2', 'id' => 'Voting' ],
			$this->msg( "communityrequests-{$this->entityType}-voting" )->text()
		);
		$out .= Html::openElement( 'div', [ 'class' => "ext-communityrequests-{$this->entityType}--voting" ] );

		// We need to wait for the full parser pass to complete to ensure all votes are counted.
		// Add a strip marker for the vote count to be added later in CommunityRequestsHooks::onParserAfterTidy().
		$out .= Html::openElement( 'p' ) . self::VOTING_STRIP_MARKER . ' ';
		$out .= $votingEnabled ?
			$this->msg( "communityrequests-{$this->entityType}-voting-info-open" )->escaped() :
			$this->msg( "communityrequests-{$this->entityType}-voting-info-closed" )->escaped();
		$out .= Html::closeElement( 'p' );

		if ( $votingEnabled ) {
			// Container for the voting button added by JavaScript.
			$out .= Html::element( 'div', [ 'class' => 'ext-communityrequests-voting-btn' ] );
		}

		// Transclude the /Votes subpage if it exists.
		$basePage = $this->config->getCanonicalEntityPageRef( $this->parser->getPage() );
		if ( $basePage ) {
			$voteSubpagePath = Title::newFromPageReference( $basePage )->getPrefixedDBkey()
				. $this->config->getVotesPageSuffix();
			$voteSubpageTitle = Title::newFromText( $voteSubpagePath );
			if ( $voteSubpageTitle->exists() ) {
				$out .= $this->parser->recursiveTagParse( '{{:' . $voteSubpagePath . '}}' );
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
}
