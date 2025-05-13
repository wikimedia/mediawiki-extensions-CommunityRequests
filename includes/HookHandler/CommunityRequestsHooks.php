<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;

class CommunityRequestsHooks implements
	GetDoubleUnderscoreIDsHook,
	LoginFormValidErrorMessagesHook,
	ParserFirstCallInitHook
{

	public const MAGIC_MACHINETRANSLATION = 'machinetranslation';

	private Config $config;
	private bool $enabled;

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
	public function onParserFirstCallInit( $parser ) {
		if ( !$this->enabled ) {
			return;
		}
		$parser->setHook( 'community-request', [ $this, 'renderRequest' ] );
		$parser->setHook( 'focus-area', [ $this, 'renderFocusArea' ] );
	}

	/** @inheritDoc */
	public function onLoginFormValidErrorMessages( array &$messages ) {
		if ( !$this->enabled ) {
			return;
		}
		$messages[] = 'communityrequests-please-log-in';
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
