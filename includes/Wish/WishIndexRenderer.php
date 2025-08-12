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
		$args = $this->getArgs();
		$output->setJsConfigVar( 'wishesData', [
			'lang' => $this->normalizeValue( 'lang', $this->parser->getTargetLanguage()->getCode() ),
			'sort' => $this->normalizeValue( 'sort', 'created' ),
			'dir' => $this->normalizeValue( 'dir', 'descending' ),
			'limit' => intval( $args['limit'] ?? 10 ) ?: 10,
		] );
		$output->addModules( [ 'ext.communityrequests.wish-index' ] );

		// TODO: generate full initial HTML with PHP
		return Html::element( 'div', [ 'class' => 'ext-communityrequests-wishes' ] );
	}

	/**
	 * Normalize and sanitize a value from the args.
	 *
	 * @param string $key
	 * @param string $default
	 * @return string
	 * @todo Could be useful elsewhere, move to AbstractTemplateRenderer?
	 */
	private function normalizeValue( string $key, string $default ): string {
		return htmlspecialchars(
			trim( $this->getArgs()[$key] ?? $default ) ?: $default
		);
	}
}
