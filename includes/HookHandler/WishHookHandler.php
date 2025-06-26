<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Extension\CommunityRequests\WishlistConfig;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserFactory;

/**
 * Hook handlers for the <wish> tag.
 */
class WishHookHandler extends CommunityRequestsHooks implements
	ParserFirstCallInitHook,
	LinksUpdateCompleteHook,
	BeforePageDisplayHook
{
	public const SESSION_KEY = 'communityrequests-intake';
	public const WISH_TRACKING_CATEGORY = 'communityrequests-wish-category';

	public function __construct(
		protected WishlistConfig $config,
		WishStore $store,
		private readonly UserFactory $userFactory,
		private readonly LinkRenderer $linkRenderer,
		Config $mainConfig
	) {
		parent::__construct( $config, $store, $mainConfig );
	}

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ) {
		if ( !$this->config->isEnabled() ) {
			return;
		}
		$parser->setHook( 'wish', $this->renderWish( ... ) );
	}

	// Creating and editing wishes

	/**
	 * Render the <wish> tag.
	 *
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @return string
	 */
	public function renderWish( $input, array $args, Parser $parser ): string {
		if ( !$this->config->isEnabled() || !$this->config->isWishPage( $parser->getPage() ) ) {
			return '';
		}

		$this->addTrackingCategory( $parser, self::WISH_TRACKING_CATEGORY );

		// Add tracking category for missing critical data.
		$requiredFields = [
			Wish::TAG_ATTR_CREATED,
			Wish::TAG_ATTR_TITLE,
			Wish::TAG_ATTR_PROPOSER,
			Wish::TAG_ATTR_BASE_LANG,
		];
		$missingFields = array_diff( $requiredFields, array_keys( $args ) );
		if ( $missingFields ) {
			$this->addTrackingCategory( $parser, self::ERROR_TRACKING_CATEGORY );
			return Html::element( 'span', [ 'class' => 'error' ],
				'Missing required field(s): ' . implode( ', ', $missingFields ) );
		}

		// These need to be set here because we need them for display in ::renderWishInternal().
		$args[ 'updated' ] = $parser->getRevisionTimestamp();
		$args[ Wish::TAG_ATTR_CREATED ] ??= $args[ 'updated' ];

		// Cache the wish data for storage after the links update.
		$parser->getOutput()->setExtensionData( self::EXT_DATA_KEY, $args );

		return $this->renderWishInternal( $input ?: '', $args, $parser );
	}

	/** @inheritDoc */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) {
		if ( !$this->config->isEnabled() || !$this->config->isWishPage( $linksUpdate->getTitle() ) ) {
			return;
		}
		$data = $linksUpdate->getParserOutput()->getExtensionData( self::EXT_DATA_KEY );
		if ( !$data ) {
			return;
		}

		$wish = Wish::newFromWikitextParams(
			$this->getCanonicalWishlistPage( $linksUpdate->getTitle() ),
			$linksUpdate->getTitle()->getPageLanguage()->getCode(),
			$data,
			$this->config,
			$this->userFactory->newFromName( $data[ Wish::TAG_ATTR_PROPOSER ] ?? '' ),
		);

		$this->store->save( $wish );
	}

	// Viewing wishes

	private function renderWishInternal( string $input, array $args, Parser $parser ): string {
		$language = $parser->getContentLanguage();

		// Title and status.
		$statusLabel = $this->config->getStatusLabelFromWikitextVal( $args[ Wish::TAG_ATTR_STATUS ] ?? '' );
		if ( $statusLabel === null ) {
			$statusLabel = 'communityrequests-status-unknown';
			$this->addTrackingCategory( $parser, self::ERROR_TRACKING_CATEGORY );
		}
		$statusChip = Html::rawElement( 'span',
			[ 'class' => 'cdx-info-chip ext-communityrequests-wish--status' ],
			Html::element( 'span', [
				'class' => 'cdx-info-chip__text',
			], $parser->msg( $statusLabel )->text() )
		);
		$titleSpan = Html::element( 'span', [
			'class' => 'ext-communityrequests-wish--title',
		], $args[ Wish::TAG_ATTR_TITLE ] );
		$headingDiv = Html::rawElement( 'div', [
			'class' => 'mw-heading mw-heading2 ext-communityrequests-wish--heading',
		], $titleSpan . $statusChip );

		// Edit and discuss buttons.
		$editWishLink = $this->getFakeButton( $parser, Title::makeTitle(
			NS_SPECIAL,
			// FIXME: ignores namespace of the wish
			'WishlistIntake/' . $parser->getPage()->getDBkey()
		), 'communityrequests-edit-wish', 'edit' );
		$discussWishLink = $this->getFakeButton( $parser, Title::makeTitle(
			NS_TALK,
			$parser->getPage()->getDBkey()
		), 'communityrequests-discuss-wish', 'speech-bubbles' );

		// Description.
		$descHeading = Html::element( 'div', [
			'class' => 'mw-heading mw-heading3',
		], $parser->msg( 'communityrequests-wish-description-heading' )->text() );
		$desc = $this->getParagraph( 'description', $parser->recursiveTagParse( $input ), true );

		// Focus area.
		$focusAreaHeading = Html::element( 'div', [
			'class' => 'mw-heading mw-heading3',
		], $parser->msg( 'communityrequests-wish-focus-area-heading' )->text() );
		// TODO: Fetch focus area title.
		$focusArea = $this->getParagraph( 'focus-area',
			$parser->msg( 'communityrequests-focus-area-unassigned' )->text()
		);

		// Wish type.
		$wishTypeHeading = Html::element( 'div', [
			'class' => 'mw-heading mw-heading3',
		], $parser->msg( 'communityrequests-wish-type-heading' )->text() );
		$wishType = $this->getParagraph( 'wish-type', $parser->msg(
			$this->config->getWishTypeLabelFromWikitextVal( $args[ Wish::TAG_ATTR_TYPE ] ?? '' ) . '-label'
		)->text() );

		// Projects.
		$projectsHeading = Html::element( 'div', [
			'class' => 'mw-heading mw-heading3',
		], $parser->msg( 'communityrequests-wish-related-heading' )->text() );
		$projectLabels = array_map( function ( $wikitextVal ) use ( $parser ) {
			$label = $this->config->getProjectLabelFromWikitextVal( $wikitextVal );
			if ( $label === null ) {
				$this->addTrackingCategory( $parser, self::ERROR_TRACKING_CATEGORY );
				return null;
			}
			return $parser->msg( $label )->text();
		}, array_filter( explode( Wish::TEMPLATE_ARRAY_DELIMITER, $args[ Wish::TAG_ATTR_PROJECTS ] ?? '' ) ) );
		$projects = $this->getParagraph( 'projects', $language->commaList( array_filter( $projectLabels ) ) );
		if ( isset( $args[ Wish::TAG_ATTR_OTHER_PROJECT ] ) ) {
			$projects .= $this->getParagraph( 'other-project', $args[ Wish::TAG_ATTR_OTHER_PROJECT ] );
		}

		// Audience.
		$audienceHeading = Html::element( 'div', [
			'class' => 'mw-heading mw-heading3',
		], $parser->msg( 'communityrequests-wish-audience-heading' )->text() );
		$audience = $this->getParagraph( 'audience', $args[ Wish::TAG_ATTR_AUDIENCE ] ?? '' );

		// Phabricator tasks.
		$tasksHeading = Html::element( 'div', [
			'class' => 'mw-heading mw-heading3',
		], $parser->msg( 'communityrequests-wish-phabricator-heading' )->text() );
		$tasks = array_map( function ( $task ) use ( $parser ) {
			$task = trim( $task );
			if ( $task === '' ) {
				return null;
			}
			if ( !preg_match( '/^T\d+$/', $task ) ) {
				$this->addTrackingCategory( $parser, self::ERROR_TRACKING_CATEGORY );
				return null;
			}
			return $this->linkRenderer->makeLink(
				new TitleValue( NS_MAIN, $task, '', 'phab' ),
				$task
			);
		}, explode( Wish::TEMPLATE_ARRAY_DELIMITER, $args[ Wish::TAG_ATTR_PHAB_TASKS ] ?? '' ) );
		$tasks = $this->getParagraph( 'phab-tasks', $language->commaList( array_filter( $tasks ) ), true );

		// Other details.
		$detailsHeading = Html::element( 'div', [
			'class' => 'mw-heading mw-heading3',
		], $parser->msg( 'communityrequests-wish-other-details-heading' )->text() );
		$proposerVal = $args[ Wish::TAG_ATTR_PROPOSER ] ?? '';
		$details = Html::rawElement( 'ul', [], implode( '', [
			$this->getListItem( 'created', $parser, $language->userTimeAndDate(
				$args[ Wish::TAG_ATTR_CREATED ], $parser->getUserIdentity()
			) ),
			$this->getListItem( 'updated', $parser, $language->userTimeAndDate(
				$args[ 'updated' ], $parser->getUserIdentity()
			) ),
			$this->getListItem( 'proposer', $parser, $parser->msg(
				'signature', $proposerVal, $proposerVal
			)->text() ),
		] ) );

		return Html::rawElement( 'div', [ 'class' => 'ext-communityrequests-wish' ],
			$headingDiv .
			$editWishLink . '&nbsp;' . $discussWishLink .
			$descHeading . $desc .
			$focusAreaHeading . $focusArea .
			$wishTypeHeading . $wishType .
			$projectsHeading . $projects .
			$audienceHeading . $audience .
			$tasksHeading . $tasks .
			$detailsHeading . $details
		);
	}

	private function getParagraph( string $field, string $text, bool $raw = false ): string {
		return Html::{ $raw ? 'rawElement' : 'element' }( 'p', [
			'class' => "ext-communityrequests-wish--$field",
		], $text );
	}

	private function getListItem( string $field, Parser $parser, string $param ): string {
		return Html::rawElement( 'li', [
			'class' => "ext-communityrequests-wish--$field",
		], $parser->msg( "communityrequests-wish-$field", $param ) );
	}

	private function getFakeButton( Parser $parser, Title $title, string $msgKey, string $icon ): string {
		return Html::rawElement( 'a',
			[
				'href' => $title->getLocalURL(),
				'class' => [
					'cdx-button', 'cdx-button--fake-button', 'cdx-button--fake-button--enabled',
					'cdx-button--action-default', 'cdx-button--weight-normal', 'cdx-button--enabled'
				],
				'role' => 'button',
			],
			Html::element( 'span',
				[
					'class' => [ 'cdx-button__icon', "ext-communityrequests-wish--$icon" ],
					'aria-hidden' => 'true',
				],
			) . $parser->msg( $msgKey )->text()
		);
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->config->isEnabled() || !$this->config->isWishPage( $out->getTitle() ) ) {
			return;
		}

		if ( $out->getRequest()->getSession()->get( self::SESSION_KEY ) ) {
			$postEditVal = $out->getRequest()->getSession()->get( self::SESSION_KEY );
			$out->getRequest()->getSession()->remove( self::SESSION_KEY );
			$out->addJsConfigVars( 'intakePostEdit', $postEditVal );
			$out->addModules( 'ext.communityrequests.intake' );
		}

		$out->addModuleStyles( 'ext.communityrequests.styles' );
	}
}
