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

		$this->addTrackingCategory( self::TRACKING_CATEGORY );

		$output = $this->parser->getOutput();
		$args = $this->getArgs();
		$output->setJsConfigVar( 'wishesData', [
			'lang' => htmlspecialchars( $args[ 'lang' ] ?? $output->getLanguage()->toBcp47Code() ),
			'sort' => $args[ 'sort' ] ?? 'created',
			'dir' => $args[ 'dir' ] ?? 'descending',
			'limit' => (int)( $args[ 'limit' ] ?? 10 ),
		] );
		$output->addModules( [ 'ext.communityrequests.wish-index' ] );

		// TODO: generate full initial HTML with PHP
		return Html::element( 'div', [ 'class' => 'ext-communityrequests-wishes' ] );
	}

	/** @inheritDoc */
	protected function getArgAliases(): array {
		return [
			'lang' => 'lang',
			'sort' => 'sort',
			'dir' => 'dir',
			'limit' => 'limit',
		];
	}
}
