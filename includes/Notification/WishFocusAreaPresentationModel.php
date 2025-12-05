<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Notification;

use MediaWiki\Message\Message;

class WishFocusAreaPresentationModel extends AbstractPresentationModel {

	/** @inheritDoc */
	public function getIconType(): string {
		return 'edit';
	}

	/** @inheritDoc */
	public function getBodyMessage(): bool|Message {
		return $this->msg( 'communityrequests-notification-wish-focusarea-change',
			$this->event->getExtraParam( 'focusAreaId' ) .
				$this->msg( 'colon-separator' )->text() . ' ' .
				$this->event->getExtraParam( 'focusAreaTitle' )
		);
	}
}
