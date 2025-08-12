<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Tests\Unit\AbstractWishlistEntityTest;
use MediaWiki\Extension\CommunityRequests\Wish\WishIndexRenderer;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\PPFrame;
use MockTitleTrait;
use Psr\Log\LoggerInterface;

/**
 * @group CommunityRequests
 * @coversDefaultClass \MediaWiki\Extension\CommunityRequests\Wish\WishIndexRenderer
 */
class WishIndexRendererTest extends AbstractWishlistEntityTest {

	use MockTitleTrait;

	/**
	 * @covers ::render
	 * @dataProvider provideTestJsVars
	 */
	public function testJsVars( array $args, array $expectedJsVars ): void {
		$output = $this->createNoOpMock( ParserOutput::class, [ 'setJsConfigVar', 'addModules' ] );
		$output->expects( $this->once() )
			->method( 'setJsConfigVar' )
			->with( 'wishesData', $expectedJsVars );
		$language = $this->createNoOpMock( Language::class, [ 'getCode' ] );
		$language->expects( $this->atMost( 1 ) )
			->method( 'getCode' )
			->willReturn( 'en' );
		$parser = $this->createNoOpMock( Parser::class, [
			'getPage', 'getOutput', 'addTrackingCategory', 'getTargetLanguage',
		] );
		$parser->expects( $this->atMost( 1 ) )
			->method( 'getPage' )
			->willReturn( $this->makeMockTitle( 'Community Wishlist/Wishes' )->toPageIdentity() );
		$parser->expects( $this->once() )
			->method( 'getOutput' )
			->willReturn( $output );
		$parser->expects( $this->atMost( 1 ) )
			->method( 'getTargetLanguage' )
			->willReturn( $language );
		$parser->expects( $this->once() )
			->method( 'addTrackingCategory' )
			->with( WishIndexRenderer::TRACKING_CATEGORY );
		$childFrame = $this->createNoOpMock( PPFrame::class, [ 'getArguments' ] );
		$childFrame->expects( $this->once() )
			->method( 'getArguments' )
			->willReturn( $args );
		$frame = $this->createNoOpMock( PPFrame::class, [ 'newChild' ] );
		$frame->expects( $this->once() )
			->method( 'newChild' )
			->willReturn( $childFrame );

		$renderer = new WishIndexRenderer(
			$this->config,
			$this->createNoOpMock( FocusAreaStore::class ),
			$this->createNoOpMock( LoggerInterface::class ),
			$this->createNoOpMock( LinkRenderer::class ),
			$parser,
			$frame,
			[]
		);

		$renderer->render();
	}

	public function provideTestJsVars(): array {
		return [
			[
				[],
				[
					'lang' => 'en',
					'sort' => 'created',
					'dir' => 'descending',
					'limit' => 10,
				]
			],
			[
				[ 'lang' => '  de ', 'sort' => 'title', 'dir' => 'ascending', 'limit' => 5 ],
				[
					'lang' => 'de',
					'sort' => 'title',
					'dir' => 'ascending',
					'limit' => 5,
				]
			],
			[
				[ 'lang' => 'fr', 'sort' => '<script>foobar</script>', 'dir' => 'descending', 'limit' => 20 ],
				[
					'lang' => 'fr',
					'sort' => '&lt;script&gt;foobar&lt;/script&gt;',
					'dir' => 'descending',
					'limit' => 20,
				]
			],
			[
				[
					'lang' => '',
					'sort' => '',
					'dir' => '',
					'limit' => '',
				],
				[
					'lang' => 'en',
					'sort' => 'created',
					'dir' => 'descending',
					'limit' => 10,
				]
			],
		];
	}
}
