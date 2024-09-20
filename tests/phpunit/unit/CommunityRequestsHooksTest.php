<?php

namespace MediaWiki\Extension\CommunityRequests\Test\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CommunityRequests\CommunityRequestsHooks;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\Hooks
 */
class CommunityRequestsHooksTest extends MediaWikiUnitTestCase {

	/**
	 * @covers MediaWiki\Extension\CommunityRequests\CommunityRequestsHooks::onParserAfterParse
	 * @dataProvider provideOnParserAfterParse
	 */
	public function testOnParserAfterParse( bool $enabled, bool $magicWordPropSet, bool $moduleAdded ): void {
		$parser = $this->createMock( Parser::class );
		$parserOutput = $this->createMock( ParserOutput::class );

		if ( !$enabled ) {
			$parser->expects( $this->never() )->method( 'getOutput' );
		} else {
			$parser->expects( $this->atLeastOnce() )
				->method( 'getOutput' )
				->willReturn( $parserOutput );
		}

		if ( $enabled && $magicWordPropSet && $moduleAdded ) {
			$parserOutput->expects( $this->once() )
				->method( 'getPageProperty' )
				->willReturn( true );
			$parserOutput->expects( $this->once() )->method( 'addModules' )
				->with( [ 'ext.communityrequests.mint' ] );
		} else {
			$parserOutput->expects( $this->never() )->method( 'addModules' );
		}

		$config = new HashConfig( [ 'CommunityRequestsEnable' => $enabled ] );
		$text = '';
		( new CommunityRequestsHooks( $config ) )->onParserAfterParse( $parser, $text, null );
	}

	public function provideOnParserAfterParse(): array {
		return [
			[ 'enabled' => false, 'magic_word_prop' => false, 'module_added' => false ],
			[ 'enabled' => false, 'magic_word_prop' => false, 'module_added' => true ],
			[ 'enabled' => false, 'magic_word_prop' => true, 'module_added' => false ],
			[ 'enabled' => false, 'magic_word_prop' => true, 'module_added' => true ],
			[ 'enabled' => true, 'magic_word_prop' => false, 'module_added' => false ],
			[ 'enabled' => true, 'magic_word_prop' => false, 'module_added' => true ],
			[ 'enabled' => true, 'magic_word_prop' => true, 'module_added' => false ],
			[ 'enabled' => true, 'magic_word_prop' => true, 'module_added' => true ],
		];
	}
}
