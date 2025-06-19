<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityRequests\Wish\SpecialWishlistIntake;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Hook\RecentChange_saveHook;
use MediaWiki\RecentChanges\RecentChange;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
class CommunityRequestsHooks implements
	ChangeTagsListActiveHook,
	ListDefinedTagsHook,
	GetDoubleUnderscoreIDsHook,
	LoginFormValidErrorMessagesHook,
	RecentChange_saveHook
{

	public const WISHLIST_CHANGE_TAG = 'community-wishlist';
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

	/** @inheritDoc */
	public function onListDefinedTags( &$tags ) {
		if ( $this->config->isEnabled() ) {
			$tags[] = self::WISHLIST_CHANGE_TAG;
		}
	}

	/** @inheritDoc */
	public function onChangeTagsListActive( &$tags ) {
		if ( $this->config->isEnabled() ) {
			$tags[] = self::WISHLIST_CHANGE_TAG;
		}
	}

	/**
	 * Adds the self::WISHLIST_CHANGE_TAG tag to recent changes
	 * if the request was made using SpecialWishlistIntake.
	 *
	 * @param RecentChange $rc
	 */
	public function onRecentChange_Save( $rc ) {
		$request = RequestContext::getMain()->getRequest();
		if ( $request->getSession()->get( SpecialWishlistIntake::SESSION_KEY ) ) {
			$rc->addTags( self::WISHLIST_CHANGE_TAG );
		}
	}
}
