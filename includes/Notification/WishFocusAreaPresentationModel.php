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
		$key = $this->event->getExtraParam( 'focusAreaUnassigned' ) ?
			'communityrequests-notification-wish-focusarea-unassigned' :
			'communityrequests-notification-wish-focusarea-change';
		return $this->msg( $key, $this->event->getExtraParam( 'focusAreaId' ) .
			$this->msg( 'colon-separator' )->text() . ' ' .
			$this->event->getExtraParam( 'focusAreaTitle' )
		);
	}
}
