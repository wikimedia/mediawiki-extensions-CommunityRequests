<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Extension\DiscussionTools\CommentUtils;
use MediaWiki\Extension\DiscussionTools\SubscriptionStore;
use MediaWiki\Page\Event\PageCreatedEvent;
use MediaWiki\Page\Event\PageCreatedListener;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;

class EventIngress extends DomainEventIngress implements PageCreatedListener {

	public function __construct(
		private readonly WishlistConfig $config,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly ?SubscriptionStore $subscriptionStore,
	) {
	}

	/**
	 * Automatically subscribe users to entity pages and their talk pages when they are created.
	 */
	public function handlePageCreatedEvent( PageCreatedEvent $event ): void {
		$identity = $event->getPageRecordAfter();
		if ( !$this->config->isEntityPage( $identity ) ||
			!$this->subscriptionStore ||
			!$this->config->isNotificationsEnabled()
		) {
			return;
		}

		$title = Title::newFromPageIdentity( $identity );

		// Auto-subscribe to the entity page.
		$this->subscriptionStore->addAutoSubscriptionForUser(
			$event->getPerformer(),
			$title,
			CommentUtils::getNewTopicsSubscriptionId( $title )
		);
		// And also its talk page.
		$talkTitle = Title::newFromLinkTarget( $this->namespaceInfo->getTalkPage( $title ) );
		$this->subscriptionStore->addAutoSubscriptionForUser(
			$event->getPerformer(),
			$talkTitle,
			CommentUtils::getNewTopicsSubscriptionId( $talkTitle )
		);
	}
}
