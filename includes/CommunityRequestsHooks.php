<?php

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Config\Config;
use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\Hook\ParserAfterParseHook;

class CommunityRequestsHooks implements GetDoubleUnderscoreIDsHook, ParserAfterParseHook {

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
}
