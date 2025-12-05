<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Notification;

use MediaWiki\Message\Message;
use MediaWiki\Title\Title;

class FocusAreaWishPresentationModel extends AbstractPresentationModel {

	public function canRender(): bool {
		$wishIdentity = Title::newFromText( $this->event->getExtraParam( 'wishPageTitle' ) );
		if ( !$wishIdentity ) {
			return false;
		}
		return parent::canRender() && $wishIdentity->exists();
	}

	/** @inheritDoc */
	public function getIconType(): string {
		return 'edit';
	}

	/** @inheritDoc */
	public function getBodyMessage(): bool|Message {
		$msgSuffix = $this->event->getExtraParam( 'removed' ) ? 'removed' : 'added';
		return $this->msg( "communityrequests-notification-focus-area-wish-$msgSuffix",
			$this->event->getExtraParam( 'wishId' ) .
				$this->msg( 'colon-separator' )->text() . ' ' .
				$this->event->getExtraParam( 'wishTitle' )
		);
	}
}
