<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Extension\CommunityRequests\AbstractRenderer;
use MediaWiki\Html\Html;

class WishIndexRenderer extends AbstractRenderer {

	protected string $entityType = 'wish-index';

	/**
	 * Render {{#CommunityRequests: wishes}}
	 *
	 * @return string
	 */
	public function render(): string {
		if ( !$this->config->isEnabled() ) {
			return '';
		}

		$this->parser->addTrackingCategory( self::TRACKING_CATEGORY );

		$output = $this->parser->getOutput();
		$output->setJsConfigVar( 'wishesData', [
			'lang' => $this->getSafeArg( 'lang', $this->parser->getTargetLanguage()->getCode() ),
			'sort' => $this->getSafeArg( 'sort', 'created' ),
			'dir' => $this->getSafeArg( 'dir', 'descending' ),
			'limit' => intval( $this->getSafeArg( 'limit', 10 ) ),
		] );
		$output->addModules( [ 'ext.communityrequests.wish-index' ] );

		// TODO: generate full initial HTML with PHP
		return Html::element( 'div', [ 'class' => 'ext-communityrequests-wishes' ] );
	}
}
