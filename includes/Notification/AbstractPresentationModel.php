<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Notification;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\Message\Message;

abstract class AbstractPresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function canRender(): bool {
		return $this->event->getTitle()->exists();
	}

	/** @inheritDoc */
	abstract public function getIconType(): string;

	/** @inheritDoc */
	public function getHeaderMessage(): Message {
		return $this->msg( 'communityrequests-notification-header' )
			->params(
				'<strong>' .
				$this->event->getExtraParam( 'entityId' ) .
				$this->msg( 'colon-separator' )->text() . ' ' .
				$this->event->getExtraParam( 'entityTitle' ) .
				'</strong>'
			);
	}

	/** @inheritDoc */
	public function getCompactHeaderMessage(): Message {
		return $this->msg( 'communityrequests-notifications-bundled' );
	}

	/** @inheritDoc */
	public function getPrimaryLink(): array|false {
		return [
			'url' => $this->event->getTitle()->getFullURL(),
			'label' => $this->event->getTitle()->getPrefixedText(),
		];
	}

	/** @inheritDoc */
	public function getSecondaryLinks(): ?array {
		// Code adapted from DiscussionTools::SubscribedNewCommentPresentationModel (MIT)
		$url = $this->event->getTitle()->getLocalURL( [
			'oldid' => 'prev',
			'diff' => $this->event->getExtraParam( 'revId' ),
		] );
		$viewChangesLink = [
			'url' => $url,
			'label' => $this->msg(
				'notification-link-text-view-changes',
				$this->getViewingUserForGender()
			)->text(),
			'description' => '',
			'icon' => 'changes',
			'prioritized' => true,
		];
		return [ $this->getAgentLink(), $viewChangesLink ];
	}
}
