<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Integration;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistStore;
use MediaWiki\Extension\CommunityRequests\Wish\SpecialWishlistIntake;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use SpecialPageTestBase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\CommunityRequests\Wish\SpecialWishlistIntake
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractRenderer
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractWishlistSpecialPage
 * @covers \MediaWiki\Extension\CommunityRequests\AbstractWishlistStore
 * @covers \MediaWiki\Extension\CommunityRequests\Wish\Wish
 */
class SpecialWishlistIntakeTest extends SpecialPageTestBase {
	use WishlistTestTrait;

	protected function getStore(): AbstractWishlistStore {
		return $this->getServiceContainer()->get( 'CommunityRequests.WishStore' );
	}

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage(): SpecialWishlistIntake {
		$services = $this->getServiceContainer();
		return new SpecialWishlistIntake(
			$services->get( 'CommunityRequests.WishlistConfig' ),
			$services->get( 'CommunityRequests.WishStore' ),
			$services->get( 'CommunityRequests.FocusAreaStore' ),
			$services->get( 'TitleParser' ),
			$services->getUserFactory(),
			$services->get( 'CommunityRequests.Logger' ),
		);
	}

	public function testLoggedOut(): void {
		$this->expectException( UserNotLoggedIn::class );
		$this->executeSpecialPage();
	}

	/**
	 * @dataProvider provideNotFoundOrInvalid
	 */
	public function testNotFoundOrInvalid( $subpage, $testDescription ): void {
		[ $html ] = $this->executeSpecialPage( $subpage, null, null, $this->getTestUser()->getAuthority() );
		$this->assertStringContainsString( 'communityrequests-wish-not-found', $html, $testDescription );
	}

	public static function provideNotFoundOrInvalid(): array {
		return [
			[ '12345', 'Should show error page when wish is not found' ],
			[ 'Not_an_ID', 'Should show error page when wish ID is invalid' ],
		];
	}

	public function testEditExistingThrowsNoException(): void {
		$wish = $this->insertTestWish();
		$this->resetCount();
		$this->executeSpecialPage( $wish->getPage()->getId(), null, 'en', $wish->getProposer() );
		$this->expectNotToPerformAssertions();
	}

	public function testEditExistingHasTranslateTags(): void {
		$wish = $this->insertTestWish(
			'Community Wishlist/W1',
			'en',
			[
				Wish::PARAM_TITLE => '<translate>Test Wish</translate>',
				Wish::PARAM_DESCRIPTION => '<translate>This is a [[test]] {{wish}}.</translate>',
				Wish::PARAM_AUDIENCE => '<translate>Example audience</translate>',
			]
		);
		$pageId = $wish->getPage()->getId();

		$sp = $this->newSpecialPage();
		$sp->loadExistingEntity( $pageId, $wish->getPage() );
		$vars = $sp->getOutput()->getJsConfigVars();
		$this->assertSame( $vars['intakeId'], $pageId );
		$this->assertSame( '<translate><!--T:1--> Test Wish</translate>', $vars['intakeData'][Wish::PARAM_TITLE] );
		$this->assertSame(
			'<translate><!--T:2--> This is a [[test]] {{wish}}.</translate>',
			$vars['intakeData'][Wish::PARAM_DESCRIPTION]
		);
		$this->assertSame(
			'<translate><!--T:3--> Example audience</translate>',
			$vars['intakeData'][Wish::PARAM_AUDIENCE]
		);
	}

	public function testSetRelevantTitle(): void {
		$this->insertTestWish( 'Community Wishlist/W1' );
		$sp = $this->newSpecialPage();
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $this->getTestUser()->getUser() );
		$context->setTitle( $sp->getPageTitle( 'W1' ) );
		$sp->setContext( $context );
		$sp->execute( 'W1' );
		$this->assertSame( 'Community Wishlist/W1', $sp->getSkin()->getRelevantTitle()->getPrefixedText() );
	}

	public function testSubmitTitleNormalization(): void {
		if ( !$this->getServiceContainer()->getExtensionRegistry()->isLoaded( 'Translate' ) ) {
			$this->markTestSkipped( 'Translate extension is not installed' );
		}

		$fauxRequest = new FauxRequest( [
			'entitytitle' => '<translate><!--T:1--> Title with {{template}} and <nowiki/></translate>',
			'status' => 'under-review',
			'type' => 'bug',
			'description' => str_repeat( 'Test string ', 10 ),
			'audience' => 'General public',
			'proposer' => $this->getTestUser()->getUser()->getName(),
			'created' => wfTimestampNow(),
			'baselang' => 'en',
		], true );
		RequestContext::getMain()->setRequest( $fauxRequest );
		$this->executeSpecialPage( '', $fauxRequest, null, $this->getTestUser()->getAuthority() );

		$wish = $this->getStore()->get( Title::newFromText( 'Community Wishlist/W1' ) );
		$this->assertSame(
			'Title with {{template}} and <nowiki/>',
			$wish->getTitle(),
			'Storage assertion failed'
		);

		$wikitext = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( $wish->getPage() )
			->getContent()
			->getText();
		$this->assertStringContainsString(
			'<translate><!--T:1--> Title with &#123;&#123;template&#125;&#125; and &lt;nowiki/&gt;</translate>',
			$wikitext,
			'Wikitext assertion failed'
		);
	}
}
