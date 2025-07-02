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
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserFactory;

/**
 * Hook handlers for the <wish> tag.
 */
class WishHookHandler extends CommunityRequestsHooks implements
	ParserFirstCallInitHook,
	LinksUpdateCompleteHook
{
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
		$statusChipHtml = Html::rawElement(
			'span',
			[ 'class' => 'cdx-info-chip ext-communityrequests-wish--status' ],
			Html::element(
				'span',
				[ 'class' => 'cdx-info-chip__text' ],
				$parser->msg( $statusLabel )->text()
			)
		);
		$titleSpan = Html::element(
			'span',
			[ 'class' => 'ext-communityrequests-wish--title' ],
			$args[ Wish::TAG_ATTR_TITLE ]
		);
		$headingHtml = Html::rawElement(
			'div',
			[ 'class' => 'mw-heading mw-heading2 ext-communityrequests-wish--heading' ],
			$titleSpan . $statusChipHtml
		);

		// Edit and discuss buttons.
		$editWishLinkHtml = $this->getFakeButton(
			$parser,
			// FIXME: ignores namespace of the wish
			Title::makeTitle( NS_SPECIAL, 'WishlistIntake/' . $parser->getPage()->getDBkey() ),
			'communityrequests-edit-wish',
			'edit'
		);
		$discussWishLinkHtml = $this->getFakeButton(
			$parser,
			Title::makeTitle( NS_TALK, $parser->getPage()->getDBkey() ),
			'communityrequests-discuss-wish',
			'speech-bubbles'
		);

		// Description.
		$descHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$parser->msg( 'communityrequests-wish-description-heading' )->text()
		);
		$descHtml = $this->getParagraphRaw( 'description', $parser->recursiveTagParse( $input ) );

		// Focus area.
		$focusAreaHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$parser->msg( 'communityrequests-wish-focus-area-heading' )->text()
		);
		// TODO: Fetch focus area title.
		$focusArea = $this->getParagraph(
			'focus-area',
			$parser->msg( 'communityrequests-focus-area-unassigned' )->text()
		);

		// Wish type.
		$wishTypeHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$parser->msg( 'communityrequests-wish-type-heading' )->text()
		);
		$wishType = $this->getParagraph(
			'wish-type',
			$parser->msg(
				$this->config->getWishTypeLabelFromWikitextVal( $args[ Wish::TAG_ATTR_TYPE ] ?? '' ) . '-label'
			)->text()
		);

		// Projects.
		$projectsHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$parser->msg( 'communityrequests-wish-related-heading' )->text()
		);
		$projectLabels = array_map( function ( $wikitextVal ) use ( $parser ) {
			$label = $this->config->getProjectLabelFromWikitextVal( $wikitextVal );
			if ( $label === null ) {
				$this->addTrackingCategory( $parser, self::ERROR_TRACKING_CATEGORY );
				return null;
			}
			return $parser->msg( $label )->text();
		}, array_filter( explode( Wish::TEMPLATE_ARRAY_DELIMITER, $args[ Wish::TAG_ATTR_PROJECTS ] ?? '' ) ) );
		// @phan-suppress-next-line SecurityCheck-DoubleEscaped
		$projects = $this->getParagraph( 'projects', $language->commaList( array_filter( $projectLabels ) ) );
		if ( isset( $args[ Wish::TAG_ATTR_OTHER_PROJECT ] ) ) {
			$projects .= $this->getParagraph( 'other-project', $args[ Wish::TAG_ATTR_OTHER_PROJECT ] );
		}

		// Audience.
		$audienceHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$parser->msg( 'communityrequests-wish-audience-heading' )->text()
		);
		$audience = $this->getParagraph( 'audience', $args[ Wish::TAG_ATTR_AUDIENCE ] ?? '' );

		// Phabricator tasks.
		$tasksHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$parser->msg( 'communityrequests-wish-phabricator-heading' )->text()
		);
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
		$tasksHtml = $this->getParagraphRaw(
			'phabtasks',
			$language->commaList( array_filter( $tasks ) )
		);

		// Other details.
		$detailsHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$parser->msg( 'communityrequests-wish-other-details-heading' )->text()
		);
		$proposerVal = $args[ Wish::TAG_ATTR_PROPOSER ] ?? '';
		$detailsHtml = Html::rawElement(
			'ul',
			[],
			$this->getListItem(
				'created',
				$parser,
				$language->userTimeAndDate( $args[ Wish::TAG_ATTR_CREATED ], $parser->getUserIdentity() )
			) .
			$this->getListItem(
				'updated',
				$parser,
				$language->userTimeAndDate( $args[ 'updated' ], $parser->getUserIdentity() )
			) .
			$this->getListItem(
				'proposer',
				$parser,
				$parser->msg( 'signature', $proposerVal, $proposerVal )->text()
			)
		);

		return Html::rawElement( 'div', [ 'class' => 'ext-communityrequests-wish' ],
			$headingHtml .
			$editWishLinkHtml . '&nbsp;' . $discussWishLinkHtml .
			$descHeading . $descHtml .
			$focusAreaHeading . $focusArea .
			$wishTypeHeading . $wishType .
			$projectsHeading . $projects .
			$audienceHeading . $audience .
			$tasksHeading . $tasksHtml .
			$detailsHeading . $detailsHtml .
			$this->getVotingSection(
				$parser,
				$this->config->isWishVotingEnabled() && in_array(
					trim( $args[ Wish::TAG_ATTR_STATUS ] ?? '' ),
					$this->config->getStatusWikitextValsEligibleForVoting()
				)
			)
		);
	}
}
