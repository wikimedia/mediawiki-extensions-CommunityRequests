<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Parser\PPNode;
use MediaWiki\Parser\PPNode_Hash_Tree;

class ArgumentExtractor {
	private const MAX_DEPTH = 30;

	public function __construct(
		private readonly ParserFactory $parserFactory,
	) {
	}

	/**
	 * Get the arguments for a call to the specified parser function.
	 *
	 * @param string $func The function name; the key from CommunityRequests.i18n.alias.php
	 * @param string $subFunc A fixed first argument
	 * @param string $text The text to search
	 * @return string[]|null
	 */
	public function getFuncArgs( string $func, string $subFunc, string $text ): ?array {
		$parser = $this->parserFactory->getInstance();
		$parser->startExternalParse(
			null, ParserOptions::newFromAnon(), Parser::OT_PLAIN
		);
		$root = $parser->preprocessToDom( $text );
		$frame = $parser->getPreprocessor()->newFrame();
		$synonyms = $parser->getFunctionSynonyms();
		return $this->getArgsFromNode( $frame, $root, $synonyms, $func, $subFunc, self::MAX_DEPTH );
	}

	private function getArgsFromNode(
		PPFrame $frame,
		PPNode $root,
		array $functionSynonyms,
		string $targetFunc,
		string $targetSubFunc,
		int $maxDepth
	): ?array {
		for ( $child = $root->getFirstChild(); $child; $child = $child->getNextSibling() ) {
			if ( $child instanceof PPNode_Hash_Tree && $child->getName() === 'template' ) {
				$bits = $child->splitTemplate();
				$title = trim( $frame->expand( $bits['title'], PPFrame::STRIP_COMMENTS ) );
				$parts = explode( ':', $title, 2 );
				if ( count( $parts ) === 2 ) {
					$name = trim( $parts[0] );
					if ( isset( $functionSynonyms[1][$name] )
						&& $functionSynonyms[1][$name] === $targetFunc
						&& trim( $parts[1] ) === $targetSubFunc
					) {
						return $this->extractArgs( $frame, $bits['parts'] );
					}
				}
			}
			if ( $maxDepth ) {
				$childSearchResult = $this->getArgsFromNode(
					$frame, $child, $functionSynonyms, $targetFunc, $targetSubFunc, $maxDepth - 1 );
				if ( $childSearchResult ) {
					return $childSearchResult;
				}
			}
		}
		return null;
	}

	private function extractArgs( PPFrame $frame, PPNode $parts ): array {
		$args = [];
		for ( $i = 0; $i < $parts->getLength(); $i++ ) {
			$part = $parts->item( $i );
			$argBits = $part->splitArg();
			$value = $frame->expand( $argBits['value'], PPFrame::RECOVER_ORIG );
			if ( $argBits['index'] === '' ) {
				// Named argument
				$name = trim( $frame->expand( $argBits['name'], PPFrame::STRIP_COMMENTS ) );
				$value = trim( $value );
			} else {
				// Numbered argument
				$name = $argBits['index'];
			}
			$args[$name] = $value;
		}
		return $args;
	}
}
