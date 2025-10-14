<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Tests\Unit\AbstractWishlistEntityTest;
use MediaWiki\Extension\CommunityRequests\Wish\WishIndexRenderer;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MockTitleTrait;
use Psr\Log\LoggerInterface;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\Wish\WishIndexRenderer
 * @covers \MediaWiki\Extension\CommunityRequests\RendererFactory
 */
class WishIndexRendererTest extends AbstractWishlistEntityTest {

	use MockTitleTrait;

	/**
	 * @dataProvider provideTestJsVars
	 */
	public function testJsVars( array $args, array $expectedJsVars ): void {
		$output = $this->createNoOpMock( ParserOutput::class, [ 'setJsConfigVar', 'addModules' ] );
		$output->expects( $this->atMost( 2 ) )
			->method( 'setJsConfigVar' )
			->willReturnCallback( function ( $key, $data ) use ( $expectedJsVars ) {
				if ( $key === 'wishesData' ) {
					$this->assertSame( $expectedJsVars, $data );
				} elseif ( $key === 'focusareasData' ) {
					$this->assertSame( [
						'unassigned' => '(communityrequests-focus-area-unassigned)',
						'FA1' => 'The first focus area',
					], $data );
				} else {
					$this->fail( "Unexpected js config var key: $key" );
				}
			} );

		$language = $this->createNoOpMock( Language::class, [ 'getCode' ] );
		$language->expects( $this->atMost( 2 ) )
			->method( 'getCode' )
			->willReturn( 'en' );
		$parser = $this->createNoOpMock( Parser::class, [
			'getPage', 'getOutput', 'addTrackingCategory', 'getTargetLanguage', 'msg',
		] );
		$parser->expects( $this->atMost( 1 ) )
			->method( 'getPage' )
			->willReturn( $this->makeMockTitle( 'Community Wishlist/Wishes' )->toPageIdentity() );
		$parser->expects( $this->once() )
			->method( 'getOutput' )
			->willReturn( $output );
		$parser->expects( $this->atMost( 2 ) )
			->method( 'getTargetLanguage' )
			->willReturn( $language );
		$parser->expects( $this->once() )
			->method( 'addTrackingCategory' )
			->with( WishIndexRenderer::TRACKING_CATEGORY );
		$parser->expects( $this->atMost( 1 ) )
			->method( 'msg' )
			->willReturnCallback( [ new FakeQqxMessageLocalizer(), 'msg' ] );
		$focusAreaStore = $this->createNoOpMock( FocusAreaStore::class, [ 'getTitlesByEntityWikitextVal' ] );
		$focusAreaStore->expects( $this->atMost( 1 ) )
			->method( 'getTitlesByEntityWikitextVal' )
			->with( 'en' )
			->willReturn( [ 'FA1' => 'The first focus area' ] );

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
			$this->createNoOpMock( WishStore::class ),
			$focusAreaStore,
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
					'statuses' => [],
					'tags' => [],
					'focusareas' => [],
					'showfilters' => false,
				]
			],
			[
				[ 'lang' => '  de ', 'sort' => 'title', 'dir' => 'ascending', 'limit' => 5, 'showfilters' => 0 ],
				[
					'lang' => 'de',
					'sort' => 'title',
					'dir' => 'ascending',
					'limit' => 5,
					'statuses' => [],
					'tags' => [],
					'focusareas' => [],
					'showfilters' => false,
				]
			],
			[
				[
					'lang' => 'fr',
					'sort' => '<script>foobar</script>',
					'dir' => 'descending',
					'limit' => 20,
					'showfilters' => '1'
				],
				[
					'lang' => 'fr',
					'sort' => '&lt;script&gt;foobar&lt;/script&gt;',
					'dir' => 'descending',
					'limit' => 20,
					'statuses' => [],
					'tags' => [],
					'focusareas' => [],
					'showfilters' => true,
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
					'statuses' => [],
					'tags' => [],
					'focusareas' => [],
					'showfilters' => false,
				]
			],
			[
				[
					'lang' => 'es',
					'sort' => 'votecount',
					'dir' => 'ascending',
					'statuses' => 'under-review, unsupported ,,declined',
					'tags' => 'admins,editing,ios',
					'focusareas' => 'FA1,FA2',
				],
				[
					'lang' => 'es',
					'sort' => 'votecount',
					'dir' => 'ascending',
					'limit' => 10,
					'statuses' => [ 'under-review', 'unsupported', 'declined' ],
					'tags' => [ 'admins', 'editing', 'ios' ],
					'focusareas' => [ 'FA1', 'FA2' ],
					'showfilters' => false,
				]
			]
		];
	}
}
