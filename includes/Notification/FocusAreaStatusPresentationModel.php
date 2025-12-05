<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Notification;

use MediaWiki\Message\Message;

class FocusAreaStatusPresentationModel extends AbstractPresentationModel {

	/** @inheritDoc */
	public function getIconType(): string {
		return 'edit';
	}

	/** @inheritDoc */
	public function getBodyMessage(): bool|Message {
		return $this->msg(
			'communityrequests-notification-focus-area-status-change',
			$this->msg( 'communityrequests-status-focus-area-' . $this->event->getExtraParam( 'new' ) ),
		);
	}
}
