<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Extension\CommunityRequests\AbstractTemplateRenderer;
use MediaWiki\Html\Html;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;

class WishTemplateRenderer extends AbstractTemplateRenderer {
	public const WISH_TRACKING_CATEGORY = 'communityrequests-wish-category';

	protected string $entityType = 'wish';

	/**
	 * Render {{#CommunityRequests: wish}}
	 *
	 * @return string HTML
	 */
	public function render(): string {
		if ( !$this->config->isEnabled()
			|| !$this->config->isWishPage( $this->parser->getPage() )
		) {
			return '';
		}

		$this->addTrackingCategory( self::WISH_TRACKING_CATEGORY );

		$args = $this->getArgs();

		// Add tracking category for missing critical data.
		$requiredFields = [
			Wish::PARAM_CREATED,
			Wish::PARAM_TITLE,
			Wish::PARAM_PROPOSER,
			Wish::PARAM_BASE_LANG,
		];
		$missingFields = array_diff( $requiredFields, array_keys( $args ) );
		if ( $missingFields ) {
			$this->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
			return Html::element( 'span', [ 'class' => 'error' ],
				'Missing required field(s): ' . implode( ', ', $missingFields ) );
		}

		// These need to be set here because we need them for display in ::renderWishInternal().
		$args[ 'updated' ] = $this->parser->getRevisionTimestamp();
		$args[ Wish::PARAM_CREATED ] ??= $args[ 'updated' ];

		$args[ 'entityType' ] = 'wish';

		// Cache the wish data for storage after the links update.
		$this->parser->getOutput()->setExtensionData( self::EXT_DATA_KEY, $args );

		return $this->renderWishInternal( $args );
	}

	/** @inheritDoc */
	protected function getArgAliases(): array {
		return array_flip( $this->config->getWishTemplateParams() );
	}

	private function renderWishInternal( array $args ): string {
		$language = $this->parser->getContentLanguage();

		// Title and status.
		$statusLabel = $this->config->getStatusLabelFromWikitextVal( $args[ Wish::PARAM_STATUS ] ?? '' );
		if ( $statusLabel === null ) {
			$statusLabel = 'communityrequests-status-unknown';
			$this->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
		}
		$statusChipHtml = Html::rawElement(
			'span',
			[ 'class' => 'cdx-info-chip ext-communityrequests-wish--status' ],
			Html::element(
				'span',
				[ 'class' => 'cdx-info-chip__text' ],
				$this->msg( $statusLabel )->text()
			)
		);
		$titleSpan = Html::element(
			'span',
			[ 'class' => 'ext-communityrequests-wish--title' ],
			$args[ Wish::PARAM_TITLE ]
		);
		$headingHtml = Html::rawElement(
			'div',
			[ 'class' => 'mw-heading mw-heading2 ext-communityrequests-wish--heading' ],
			$titleSpan . $statusChipHtml
		);

		// Edit and discuss buttons.
		$editWishLinkHtml = $this->getFakeButton(
			// FIXME: ignores namespace of the wish
			Title::makeTitle( NS_SPECIAL, 'WishlistIntake/' . $this->parser->getPage()->getDBkey() ),
			'communityrequests-edit-wish',
			'edit'
		);
		$discussWishLinkHtml = $this->getFakeButton(
			Title::makeTitle( NS_TALK, $this->parser->getPage()->getDBkey() ),
			'communityrequests-discuss-wish',
			'speech-bubbles'
		);

		// Description.
		$descHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$this->msg( 'communityrequests-wish-description-heading' )->text()
		);
		$descHtml = $this->getParagraphRaw(
			'description',
			$this->parser->recursiveTagParse( $args[ 'description' ] ?? '' )
		);

		// Focus area.
		$focusAreaHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$this->msg( 'communityrequests-wish-focus-area-heading' )->text()
		);
		$focusArea = $this->getParagraphRaw(
			'focus-area',
			$this->getFocusAreaLink( $args[ Wish::PARAM_FOCUS_AREA ] ?? null )
		);

		// Wish type.
		$wishTypeHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$this->msg( 'communityrequests-wish-type-heading' )->text()
		);
		$wishType = $this->getParagraph(
			'wish-type',
			$this->msg(
				$this->config->getWishTypeLabelFromWikitextVal( $args[ Wish::PARAM_TYPE ] ?? '' ) . '-label'
			)->text()
		);

		// Projects.
		$projectsHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$this->msg( 'communityrequests-wish-related-heading' )->text()
		);
		$projectLabels = array_map( function ( $wikitextVal ) {
			$label = $this->config->getProjectLabelFromWikitextVal( $wikitextVal );
			if ( $label === null ) {
				$this->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
				return null;
			}
			return $this->msg( $label )->text();
		}, array_filter( explode( Wish::TEMPLATE_ARRAY_DELIMITER, $args[ Wish::PARAM_PROJECTS ] ?? '' ) ) );
		// @phan-suppress-next-line SecurityCheck-DoubleEscaped
		$projects = $this->getParagraph( 'projects', $language->commaList( array_filter( $projectLabels ) ) );
		if ( isset( $args[ Wish::PARAM_OTHER_PROJECT ] ) ) {
			$projects .= $this->getParagraph( 'other-project', $args[ Wish::PARAM_OTHER_PROJECT ] );
		}

		// Audience.
		$audienceHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$this->msg( 'communityrequests-wish-audience-heading' )->text()
		);
		$audience = $this->getParagraph( 'audience', $args[ Wish::PARAM_AUDIENCE ] ?? '' );

		// Phabricator tasks.
		$tasksHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$this->msg( 'communityrequests-wish-phabricator-heading' )->text()
		);
		$tasks = array_map( function ( $task ) {
			$task = trim( $task );
			if ( $task === '' ) {
				return null;
			}
			if ( !preg_match( '/^T\d+$/', $task ) ) {
				$this->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
				return null;
			}
			return $this->linkRenderer->makeLink(
				new TitleValue( NS_MAIN, $task, '', 'phab' ),
				$task
			);
		}, explode( Wish::TEMPLATE_ARRAY_DELIMITER, $args[ Wish::PARAM_PHAB_TASKS ] ?? '' ) );
		$tasksHtml = $this->getParagraphRaw(
			'phabtasks',
			$language->commaList( array_filter( $tasks ) )
		);

		// Other details.
		$detailsHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$this->msg( 'communityrequests-wish-other-details-heading' )->text()
		);
		$proposerVal = $args[ Wish::PARAM_PROPOSER ] ?? '';
		$user = $this->parser->getUserIdentity();
		$detailsHtml = Html::rawElement(
			'ul',
			[],
			$this->getListItem(
				'created',
				$language->userTimeAndDate( $args[ Wish::PARAM_CREATED ], $user )
			) .
			$this->getListItem(
				'updated',
				$language->userTimeAndDate( $args[ 'updated' ], $user )
			) .
			$this->getListItem(
				'proposer',
				$this->msg( 'signature', $proposerVal, $proposerVal )->text()
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
				$this->config->isWishVotingEnabled() && in_array(
					trim( $args[ Wish::PARAM_STATUS ] ?? '' ),
					$this->config->getStatusWikitextValsEligibleForVoting()
				)
			)
		);
	}

	/**
	 * Get a link to the specified focus area or a suitable placeholder
	 *
	 * @param string|null $focusAreaArg The focus area input parameter
	 * @return string HTML
	 */
	private function getFocusAreaLink( ?string $focusAreaArg ): string {
		if ( $focusAreaArg ) {
			$id = $this->focusAreaStore->getIdFromInput( $focusAreaArg );
			$pageIdentity = Title::newFromText(
				$this->focusAreaStore->getPagePrefix() . $id
			);
			$entity = $this->focusAreaStore->get(
				$pageIdentity,
				$this->parser->getContentLanguage()->getCode()
			);
			if ( $entity ) {
				return $this->linkRenderer->makeKnownLink(
					$pageIdentity,
					$entity->getTitle()
				);
			} else {
				// Not found -- just show plain text
				return htmlspecialchars( $focusAreaArg, ENT_NOQUOTES );
			}
		} else {
			return $this->msg( 'communityrequests-focus-area-unassigned' )->escaped();
		}
	}
}
