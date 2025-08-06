<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Extension\CommunityRequests\AbstractTemplateRenderer;
use MediaWiki\Html\Html;

class WishIndexTemplateRenderer extends AbstractTemplateRenderer {

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
		$args = $this->getArgs();
		$output->setJsConfigVar( 'wishesData', [
			'lang' => htmlspecialchars( trim( $args['lang'] ?? $this->parser->getTargetLanguage()->getCode() ) ),
			'sort' => htmlspecialchars( trim( $args['sort'] ?? 'created' ) ),
			'dir' => htmlspecialchars( trim( $args['dir'] ?? 'descending' ) ),
			'limit' => intval( $args['limit'] ?? 10 ),
		] );
		$output->addModules( [ 'ext.communityrequests.wish-index' ] );

		// TODO: generate full initial HTML with PHP
		return Html::element( 'div', [ 'class' => 'ext-communityrequests-wishes' ] );
	}

	/** @inheritDoc */
	protected function getArgAliases(): array {
		return [];
	}
}
