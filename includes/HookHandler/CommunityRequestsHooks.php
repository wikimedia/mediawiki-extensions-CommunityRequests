<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CommunityRequestsHooks implements GetDoubleUnderscoreIDsHook, LoginFormValidErrorMessagesHook {

	public const MAGIC_MACHINETRANSLATION = 'machinetranslation';
	public const ERROR_TRACKING_CATEGORY = 'communityrequests-error-category';

	public function __construct( protected WishlistConfig $config, protected ?LoggerInterface $logger = null ) {
		if ( $this->logger === null ) {
			$this->logger = new NullLogger();
		}
	}

	/** @inheritDoc */
	public function onGetDoubleUnderscoreIDs( &$doubleUnderscoreIDs ) {
		if ( !$this->config->isEnabled() ) {
			return;
		}
		$doubleUnderscoreIDs[] = self::MAGIC_MACHINETRANSLATION;
	}

	/** @inheritDoc */
	public function onParserAfterParse( $parser, &$text, $stripState ) {
		if ( !$this->config->isEnabled() ) {
			return;
		}
		if ( $parser->getOutput()->getPageProperty( self::MAGIC_MACHINETRANSLATION ) !== null ) {
			$parser->getOutput()->addModules( [ 'ext.communityrequests.mint' ] );
		}
	}

	/** @inheritDoc */
	public function onLoginFormValidErrorMessages( array &$messages ) {
		if ( !$this->config->isEnabled() ) {
			return;
		}
		$messages[] = 'communityrequests-please-log-in';
	}
}
