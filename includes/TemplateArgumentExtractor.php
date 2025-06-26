<?php

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Linker\LinkTarget;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Parser\PPNode;
use MediaWiki\Parser\PPNode_Hash_Tree;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;

class TemplateArgumentExtractor {
	private const MAX_DEPTH = 30;

	public function __construct(
		private ParserFactory $parserFactory,
		private TitleParser $titleParser
	) {
	}

	/**
	 * Get the template arguments for a call to the specified template.
	 *
	 * @param LinkTarget $targetTitle
	 * @param string $text
	 * @return string[]|null
	 */
	public function getArgs( LinkTarget $targetTitle, string $text ) {
		$parser = $this->parserFactory->getInstance();
		$parser->startExternalParse(
			null, ParserOptions::newFromAnon(), Parser::OT_PLAIN
		);
		$root = $parser->preprocessToDom( $text );
		$frame = $parser->getPreprocessor()->newFrame();
		return $this->getArgsFromNode( $frame, $root, $targetTitle, self::MAX_DEPTH );
	}

	private function getArgsFromNode(
		PPFrame $frame,
		PPNode $root,
		LinkTarget $targetTitle,
		int $maxDepth
	): ?array {
		for ( $child = $root->getFirstChild(); $child; $child = $child->getNextSibling() ) {
			if ( $child instanceof PPNode_Hash_Tree && $child->getName() === 'template' ) {
				$bits = $child->splitTemplate();
				$title = null;
				try {
					$title = $this->titleParser->parseTitle(
						trim( $frame->expand( $bits['title'], PPFrame::STRIP_COMMENTS ) ),
						NS_TEMPLATE
					);
				} catch ( MalformedTitleException ) {
				}
				if ( $title && $targetTitle->isSameLinkAs( $title ) ) {
					return $this->extractArgs( $frame, $bits['parts'] );
				}
			}
			if ( $maxDepth ) {
				$childSearchResult = $this->getArgsFromNode(
					$frame, $child, $targetTitle, $maxDepth - 1 );
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
