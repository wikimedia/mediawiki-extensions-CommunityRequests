<?php

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\TemplateArgumentExtractor;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CommunityRequests\TemplateArgumentExtractor
 */
class TemplateArgumentExtractorTest extends MediaWikiIntegrationTestCase {
	public static function provideGetArgs() {
		return [
			'simple match' => [
				'{{tgt|a|b=c}}',
				[
					1 => 'a',
					'b' => 'c',
				],
			],
			'non-match' => [
				'{{not|b=c}}',
				null,
			],
			'conventional space trimming, from named arguments only' => [
				'{{tgt| a | b = c}}',
				[
					1 => ' a ',
					'b' => 'c',
				]
			],
			'no throw on invalid title in input text' => [
				'{{__|a|b}}',
				null,
			],
			'nowiki' => [
				'{{tgt|a = <nowiki>}}</nowiki>}}',
				[ 'a' => '<nowiki>}}</nowiki>' ],
			],
			'pre' => [
				'{{tgt|a = <pre>b</pre>}}',
				[ 'a' => '<pre>b</pre>' ],
			],
			'revisiontimestamp' => [
				'{{tgt|a={{REVISIONTIMESTAMP}}}}',
				[ 'a' => '{{REVISIONTIMESTAMP}}' ],
			],
			'distractor' => [
				'{{not|a=b}}{{tgt|c=d}}',
				[ 'c' => 'd' ],
			],
			'empty args' => [
				'{{tgt}}',
				[],
			],
			'comment in template name' => [
				'{{tgt <!-- this is the target -->|a=b}}',
				[ 'a' => 'b' ],
			],
			'comment in argument name' => [
				'{{tgt|a <!-- comment -->=b}}',
				[ 'a' => 'b' ],
			],
			'comment in value' => [
				'{{tgt|a=b <!-- comment -->}}',
				[ 'a' => 'b <!-- comment -->' ],
			],
			'target template at second level' => [
				'{{not|{{tgt|a=b}}}}',
				[ 'a' => 'b' ],
			],
			'depth exceeded error' => [
				str_repeat( '{{not|', 15 ) .
					'{{tgt|a=b}}' .
					str_repeat( '}}', 15 ),
				null,
			],
		];
	}

	/**
	 * @dataProvider provideGetArgs
	 * @param string $input
	 * @param array $expected
	 */
	public function testGetArgs( $input, $expected ) {
		$services = $this->getServiceContainer();
		$titleParser = $services->getTitleParser();
		$extractor = new TemplateArgumentExtractor(
			$services->getParserFactory(),
			$services->getTitleParser()
		);
		$targetObject = $titleParser->parseTitle( 'tgt', NS_TEMPLATE );
		$result = $extractor->getArgs( $targetObject, $input );
		$this->assertEquals( $expected, $result );
	}
}
