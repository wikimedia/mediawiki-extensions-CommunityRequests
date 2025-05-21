<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;

class CommunityRequestsHooks implements GetDoubleUnderscoreIDsHook, LoginFormValidErrorMessagesHook {

	public const MAGIC_MACHINETRANSLATION = 'machinetranslation';
	public const ERROR_TRACKING_CATEGORY = 'communityrequests-error-category';

	protected Config $config;
	protected bool $enabled;

	public function __construct( Config $config ) {
		$this->config = $config;
		$this->enabled = $this->config->get( 'CommunityRequestsEnable' );
	}

	/** @inheritDoc */
	public function onGetDoubleUnderscoreIDs( &$doubleUnderscoreIDs ) {
		if ( !$this->enabled ) {
			return;
		}
		$doubleUnderscoreIDs[] = self::MAGIC_MACHINETRANSLATION;
	}

	/** @inheritDoc */
	public function onParserAfterParse( $parser, &$text, $stripState ) {
		if ( !$this->enabled ) {
			return;
		}
		if ( $parser->getOutput()->getPageProperty( self::MAGIC_MACHINETRANSLATION ) !== null ) {
			$parser->getOutput()->addModules( [ 'ext.communityrequests.mint' ] );
		}
	}

	/** @inheritDoc */
	public function onLoginFormValidErrorMessages( array &$messages ) {
		if ( !$this->enabled ) {
			return;
		}
		$messages[] = 'communityrequests-please-log-in';
	}
}
