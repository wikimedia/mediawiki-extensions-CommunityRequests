<?php

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Parser\PPNode;
use MediaWiki\Title\Title;
use Psr\Log\LoggerInterface;

/**
 * The base class for wish/focus area parser function implementations
 */
abstract class AbstractTemplateRenderer {
	public const ERROR_TRACKING_CATEGORY = 'communityrequests-error-category';
	public const EXT_DATA_KEY = 'CommunityRequests-ext-data';

	/** @var string This is overridden by subclasses to give the entity type */
	protected string $entityType = '';

	/** @var string[]|null Lazy-initialized key-value pairs from the parser */
	private ?array $args = null;

	/**
	 * @param WishlistConfig $config
	 * @param LoggerInterface $logger
	 * @param LinkRenderer $linkRenderer
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param (string|PPNode)[] $parts The parser function arguments in unexpanded form
	 */
	public function __construct(
		protected WishlistConfig $config,
		protected LoggerInterface $logger,
		protected LinkRenderer $linkRenderer,
		protected Parser $parser,
		protected PPFrame $frame,
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
	 * @return \MediaWiki\Message\Message
	 */
	protected function msg( string $msg, ...$params ) {
		return $this->parser->msg( $msg, ...$params );
	}

	/**
	 * Get the associative array of template arguments indexed by the names
	 * given in the source.
	 *
	 * @return string[]
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
	 * Get the template arguments by their canonical names
	 *
	 * @return array
	 */
	protected function getArgs(): array {
		$aliases = $this->getArgAliases();
		$mappedArgs = [];
		foreach ( $this->getUnmappedArgs() as $name => $value ) {
			if ( isset( $aliases[ strtolower( $name ) ] ) ) {
				$name = $aliases[ strtolower( $name ) ];
			}
			$mappedArgs[$name] = $value;
		}
		return $mappedArgs;
	}

	/**
	 * Get the map of template argument aliases to their canonical names
	 *
	 * @return array
	 */
	abstract protected function getArgAliases(): array;

	// HTML helpers for rendering wish or focus area pages.

	protected function getParagraph( string $field, string $text ): string {
		return Html::element(
			'p',
			[ 'class' => "ext-communityrequests-{$this->entityType}--$field" ],
			$text
		);
	}

	protected function getParagraphRaw( string $field, string $html ): string {
		return Html::rawElement(
			'p',
			[ 'class' => "ext-communityrequests-{$this->entityType}--$field" ],
			$html
		);
	}

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
					'cdx-button', 'cdx-button--fake-button', 'cdx-button--fake-button--enabled',
					'cdx-button--action-default', 'cdx-button--weight-normal', 'cdx-button--enabled'
				],
				'role' => 'button',
			],
			Html::element( 'span',
				[
					'class' => [ 'cdx-button__icon', "ext-communityrequests-{$this->entityType}--$icon" ],
					'aria-hidden' => 'true',
				],
			) . $this->msg( $msgKey )->escaped()
		);
	}

	protected function getVotingSection( bool $votingEnabled, string $msgKey = 'wish' ): string {
		$out = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading2' ],
			$this->msg( "communityrequests-$msgKey-voting" )->text()
		);

		// TODO: Vote counting doesn't work yet (T388220)
		$out .= $this->getParagraphRaw( 'voting-desc',
			$this->msg( "communityrequests-$msgKey-voting-info", 0, 0 )->parse() . ' ' . (
			$votingEnabled ?
				$this->msg( "communityrequests-$msgKey-voting-info-open" )->escaped() :
				$this->msg( "communityrequests-$msgKey-voting-info-closed" )->escaped()
			)
		);

		if ( $votingEnabled ) {
			// TODO: add an cwaction=vote page for no-JS users, or something.
			$votingButton = Html::element(
				'button',
				[
					'class' => [ 'cdx-button', 'cdx-button--action-progressive', 'cdx-button--weight-primary' ],
					'type' => 'button',
					'disabled' => 'disabled',
				],
				$this->msg( "communityrequests-support-$msgKey" )->text()
			);
			$votingSection = Html::rawElement(
				'div',
				[ 'class' => 'ext-communityrequests-voting-btn' ],
				$votingButton
			);
			$out .= $votingSection;
		}

		// Transclude the /Votes subpage if it exists.
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		$voteSubpagePath = Title::newFromPageReference( $this->parser->getPage() )->getPrefixedDBkey() .
			$this->config->getVotesPageSuffix();
		$voteSubpageTitle = Title::newFromText( $voteSubpagePath );
		if ( $voteSubpageTitle->exists() ) {
			$out .= $this->parser->recursiveTagParse( '{{:' . $voteSubpagePath . '}}' );
		}

		return $out;
	}

	/**
	 * Adds a tracking category to the parser if the page is a wish or focus area page.
	 *
	 * @param string $category
	 */
	protected function addTrackingCategory( string $category ): void {
		if ( $this->config->isWishOrFocusAreaPage( $this->parser->getPage() ) ) {
			$this->parser->addTrackingCategory( $category );
		}
	}
}
