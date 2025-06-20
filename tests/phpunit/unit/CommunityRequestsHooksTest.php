<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Test\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWikiUnitTestCase;

/**
 * @group CommunityRequests
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks
 */
class CommunityRequestsHooksTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::onParserAfterParse
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

		$serviceOptions = new ServiceOptions( WishlistConfig::CONSTRUCTOR_OPTIONS, [
			WishlistConfig::CONFIG_ENABLED => $enabled,
			WishlistConfig::CONFIG_HOMEPAGE => '',
			WishlistConfig::CONFIG_WISH_CATEGORY => '',
			WishlistConfig::CONFIG_WISH_PAGE_PREFIX => '',
			WishlistConfig::CONFIG_FOCUS_AREA_PAGE_PREFIX => '',
			WishlistConfig::CONFIG_WISH_INDEX_PAGE => '',
			WishlistConfig::CONFIG_WISH_TEMPLATE => [],
			WishlistConfig::CONFIG_WISH_TYPES => [],
			WishlistConfig::CONFIG_PROJECTS => [],
			WishlistConfig::CONFIG_STATUSES => [],
		] );
		$config = new WishlistConfig( $serviceOptions );
		$text = '';
		( new CommunityRequestsHooks( $config ) )->onParserAfterParse( $parser, $text, null );
	}

	public static function provideOnParserAfterParse(): array {
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
