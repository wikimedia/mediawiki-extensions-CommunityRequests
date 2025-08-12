<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\ArgumentExtractor;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CommunityRequests\ArgumentExtractor
 */
class ArgumentExtractorTest extends MediaWikiIntegrationTestCase {
	public static function provideGetArgs(): array {
		return [
			'simple match' => [
				'{{#CommunityRequests: wish|a|b=c}}',
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
				'{{#CommunityRequests:wish| a | b = c}}',
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
				'{{#CommunityRequests:wish|a = <nowiki>}}</nowiki>}}',
				[ 'a' => '<nowiki>}}</nowiki>' ],
			],
			'pre' => [
				'{{#CommunityRequests:wish|a = <pre>b</pre>}}',
				[ 'a' => '<pre>b</pre>' ],
			],
			'revisiontimestamp' => [
				'{{#CommunityRequests:wish|a={{REVISIONTIMESTAMP}}}}',
				[ 'a' => '{{REVISIONTIMESTAMP}}' ],
			],
			'distractor' => [
				'{{not|a=b}}{{#CommunityRequests:wish|c=d}}',
				[ 'c' => 'd' ],
			],
			'empty args' => [
				'{{#CommunityRequests:wish}}',
				[],
			],
			'comment in template name' => [
				'{{#CommunityRequests: wish <!-- this is the target -->|a=b}}',
				[ 'a' => 'b' ],
			],
			'comment in argument name' => [
				'{{#CommunityRequests:wish|a <!-- comment -->=b}}',
				[ 'a' => 'b' ],
			],
			'comment in value' => [
				'{{#CommunityRequests:wish|a=b <!-- comment -->}}',
				[ 'a' => 'b <!-- comment -->' ],
			],
			'target template at second level' => [
				'{{not|{{#CommunityRequests:wish|a=b}}}}',
				[ 'a' => 'b' ],
			],
			'depth exceeded error' => [
				str_repeat( '{{not|', 15 ) .
					'{{#CommunityRequests:wish|a=b}}' .
					str_repeat( '}}', 15 ),
				null,
			],
		];
	}

	/**
	 * @dataProvider provideGetArgs
	 * @param string $input
	 * @param ?array $expected
	 */
	public function testGetArgs( string $input, ?array $expected ) {
		$services = $this->getServiceContainer();
		$extractor = new ArgumentExtractor(
			$services->getParserFactory(),
		);
		$result = $extractor->getFuncArgs( 'communityrequests', 'wish', $input );
		$this->assertEquals( $expected, $result );
	}
}
