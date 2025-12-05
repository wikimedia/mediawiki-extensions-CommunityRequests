<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Notification;

use MediaWiki\Message\Message;

class WishStatusPresentationModel extends AbstractPresentationModel {

	/** @inheritDoc */
	public function getIconType(): string {
		return 'edit';
	}

	/** @inheritDoc */
	public function getBodyMessage(): bool|Message {
		return $this->msg(
			'communityrequests-notification-wish-status-change',
			$this->msg( 'communityrequests-status-wish-' . $this->event->getExtraParam( 'new' ) ),
		);
	}
}
