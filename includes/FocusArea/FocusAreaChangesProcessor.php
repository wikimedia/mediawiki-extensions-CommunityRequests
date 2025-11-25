<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\FocusArea;

use MediaWiki\Extension\CommunityRequests\AbstractChangesProcessor;

class FocusAreaChangesProcessor extends AbstractChangesProcessor {

	/** @inheritDoc */
	protected function getFields(): array {
		return array_merge( parent::getFields(), [
			FocusArea::PARAM_SHORT_DESCRIPTION => null,
			FocusArea::PARAM_OWNERS => null,
			FocusArea::PARAM_VOLUNTEERS => null,
		] );
	}
}
