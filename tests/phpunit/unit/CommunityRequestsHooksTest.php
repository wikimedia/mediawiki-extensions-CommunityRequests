<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Test\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\MainConfigNames;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleParser;
use MediaWikiUnitTestCase;

/**
 * @group CommunityRequests
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\HookHandler\CommunityRequestsHooks
 */
class CommunityRequestsHooksTest extends MediaWikiUnitTestCase {

	use MockServiceDependenciesTrait;

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
			WishlistConfig::ENABLED => $enabled,
			WishlistConfig::HOMEPAGE => '',
			WishlistConfig::WISH_CATEGORY => '',
			WishlistConfig::WISH_PAGE_PREFIX => '',
			WishlistConfig::WISH_INDEX_PAGE => '',
			WishlistConfig::WISH_TEMPLATE => [],
			WishlistConfig::WISH_TYPES => [],
			WishlistConfig::FOCUS_AREA_CATEGORY => '',
			WishlistConfig::FOCUS_AREA_PAGE_PREFIX => '',
			WishlistConfig::FOCUS_AREA_INDEX_PAGE => '',
			WishlistConfig::FOCUS_AREA_TEMPLATE => [],
			WishlistConfig::PROJECTS => [],
			WishlistConfig::STATUSES => [],
			WishlistConfig::SUPPORT_TEMPLATE => '',
			WishlistConfig::VOTES_PAGE_SUFFIX => '',
			WishlistConfig::WISH_VOTING_ENABLED => true,
			WishlistConfig::FOCUS_AREA_VOTING_ENABLED => true,
		] );
		$config = new WishlistConfig(
			$serviceOptions,
			$this->newServiceInstance( TitleParser::class, [ 'localInterwikis' => [] ] ),
			$this->newServiceInstance( TitleFormatter::class, [] )
		);
		$text = '';
		$wishStoreMock = $this->newServiceInstance( WishStore::class, [] );
		$mainConfig = new HashConfig( [ MainConfigNames::PageLanguageUseDB => true ] );
		( new CommunityRequestsHooks( $config, $wishStoreMock, $mainConfig ) )
			->onParserAfterParse( $parser, $text, null );
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
