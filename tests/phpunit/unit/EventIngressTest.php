<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Tests\Unit;

use MediaWiki\Extension\CommunityRequests\EventIngress;
use MediaWiki\Extension\DiscussionTools\SubscriptionStore;
use MediaWiki\Page\Event\PageCreatedEvent;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\NamespaceInfo;
use MediaWikiUnitTestCase;
use MockTitleTrait;

/**
 * @group CommunityRequests
 * @covers \MediaWiki\Extension\CommunityRequests\EventIngress
 */
class EventIngressTest extends MediaWikiUnitTestCase {

	use MockTitleTrait;
	use MockAuthorityTrait;
	use MockWishlistConfigTrait;

	public function testAutoSubscribe(): void {
		if ( !class_exists( SubscriptionStore::class ) ) {
			$this->markTestSkipped( 'Requires DiscussionTools' );
		}
		$authority = $this->mockRegisteredUltimateAuthority();
		$title = $this->makeMockTitle( 'Community Wishlist/W123' );
		$talkTitle = $this->makeMockTitle( 'Talk:Community Wishlist/W123' );
		$title->method( 'isNewPage' )->willReturn( true );
		$subscriptionStore = $this->createNoopMock( SubscriptionStore::class, [ 'addAutoSubscriptionForUser' ] );
		$subscriptionStore->expects( $this->exactly( 2 ) )
			->method( 'addAutoSubscriptionForUser' )
			->with(
				$this->identicalTo( $authority->getUser() ),
				$this->anything(),
				$this->logicalOr(
					$this->equalTo( 'p-topics-0:Community_Wishlist/W123' ),
					$this->equalTo( 'p-topics-1:Community_Wishlist/W123' )
				)
			);
		$event = $this->createNoOpMock( PageCreatedEvent::class, [ 'getPerformer', 'getPageRecordAfter' ] );
		$event->expects( $this->exactly( 2 ) )
			->method( 'getPerformer' )
			->willReturn( $authority->getUser() );
		$event->expects( $this->once() )
			->method( 'getPageRecordAfter' )
			->willReturn( $title->toPageRecord() );
		$namespaceInfo = $this->createNoOpMock( NamespaceInfo::class, [ 'getTalkPage' ] );
		$namespaceInfo->expects( $this->once() )
			->method( 'getTalkPage' )
			->willReturn( $talkTitle );
		$ingress = new EventIngress( $this->getConfig(), $namespaceInfo, $subscriptionStore );
		$ingress->handlePageCreatedEvent( $event );
	}
}
