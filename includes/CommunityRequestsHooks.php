<?php

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Config\Config;
use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;

class CommunityRequestsHooks implements GetDoubleUnderscoreIDsHook, ParserFirstCallInitHook {

	public const MAGIC_MACHINETRANSLATION = 'MACHINETRANSLATION';

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/** @inheritDoc */
	public function onGetDoubleUnderscoreIDs( &$doubleUnderscoreIDs ) {
		if ( !$this->config->get( 'CommunityRequestsEnable' ) ) {
			return;
		}
		$doubleUnderscoreIDs[] = self::MAGIC_MACHINETRANSLATION;
	}

	/** @inheritDoc */
	public function onParserAfterParse( $parser, &$text, $stripState ) {
		if ( !$this->config->get( 'CommunityRequestsEnable' ) ) {
			return;
		}
		if ( $parser->getOutput()->getPageProperty( self::MAGIC_MACHINETRANSLATION ) !== null ) {
			$parser->getOutput()->addModules( [ 'ext.communityrequests.mint' ] );
		}
	}

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ) {
		if ( !$this->config->get( 'CommunityRequestsEnable' ) ) {
			return;
		}
		$parser->setHook( 'community-request', [ $this, 'renderRequest' ] );
		$parser->setHook( 'focus-area', [ $this, 'renderFocusArea' ] );
	}

	/**
	 * Render the <community-request> parser function, persisting the data to the database.
	 *
	 * @param string|null $text
	 * @param array $params
	 * @param Parser $parser
	 * @return string
	 */
	public function renderRequest( ?string $text, array $params, Parser $parser ): string {
		// TODO: Implement
		return '';
	}

	/**
	 * Render the <focus-area> parser function, persisting the data to the database.
	 *
	 * @param string|null $text
	 * @param array $params
	 * @param Parser $parser
	 * @return string
	 */
	public function renderFocusArea( ?string $text, array $params, Parser $parser ): string {
		// TODO: Implement
		return '';
	}
}
