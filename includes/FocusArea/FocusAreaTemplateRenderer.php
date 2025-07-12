<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\FocusArea;

use MediaWiki\Extension\CommunityRequests\AbstractTemplateRenderer;
use MediaWiki\Html\Html;

class FocusAreaTemplateRenderer extends AbstractTemplateRenderer {
	public const FOCUS_AREA_TRACKING_CATEGORY = 'communityrequests-focus-area-category';

	protected string $entityType = 'focus-area';

	/** @inheritDoc */
	protected function getArgAliases(): array {
		return array_flip( $this->config->getFocusAreaTemplateParams() );
	}

	/**
	 * Render {{#CommunityRequests: focus-area}}
	 *
	 * @return string HTML
	 */
	public function render(): string {
		$args = $this->getArgs();
		$args[ 'description' ] ??= '';
		if ( !$this->config->isEnabled()
			|| !$this->config->isFocusAreaPage( $this->parser->getPage() ) ) {
			return '';
		}

		$this->addTrackingCategory( self::FOCUS_AREA_TRACKING_CATEGORY );

		// Add tracking category for missing critical data.
		$requiredFields = [
			FocusArea::PARAM_CREATED,
			FocusArea::PARAM_TITLE,
			FocusArea::PARAM_BASE_LANG,
		];
		$missingFields = array_diff( $requiredFields, array_keys( $args ) );
		if ( $missingFields ) {
			$this->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
			return Html::element(
				'span',
				[ 'class' => 'error' ],
				'Missing required field(s): ' . implode( ', ', $missingFields )
			);
		}

		// These need to be set here because we need them for display in ::renderFocusAreaInternal().
		$args[ 'updated' ] = $this->parser->getRevisionTimestamp();
		$args[ FocusArea::PARAM_CREATED ] ??= $args[ 'updated' ];

		$args[ 'entityType' ] = 'focus-area';

		$this->logger->debug( __METHOD__ . ": Rendering focus area. {0}", [ json_encode( $args ) ] );
		$this->parser->getOutput()->setExtensionData( self::EXT_DATA_KEY, $args );

		return $this->renderFocusAreaInternal( $args );
	}

	private function renderFocusAreaInternal( array $args ): string {
		$language = $this->parser->getContentLanguage();
		$out = '';

		// Title and status.
		$statusLabel = $this->config->getStatusLabelFromWikitextVal( $args[ FocusArea::PARAM_STATUS ] ?? '' );
		if ( $statusLabel === null ) {
			$statusLabel = 'communityrequests-status-unknown';
			$this->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
		}
		$statusChipHtml = Html::rawElement(
			'span',
			[ 'class' => 'cdx-info-chip ext-communityrequests-focus-area--status' ],
			Html::element(
				'span',
				[ 'class' => 'cdx-info-chip__text' ],
				$this->parser->msg( $statusLabel )->text()
			)
		);
		$titleSpan = Html::element(
			'span',
			[ 'class' => 'ext-communityrequests-focus-area--title' ],
			$args[ FocusArea::PARAM_TITLE ]
		);
		$out .= Html::rawElement(
			'div',
			[ 'class' => 'mw-heading mw-heading2 ext-communityrequests-focus-area--heading' ],
			$titleSpan . $statusChipHtml
		);

		// Description.
		$out .= Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$this->parser->msg( 'communityrequests-wish-description-heading' )->text()
		);
		$out .= $this->getParagraphRaw(
			'description',
			$this->parser->recursiveTagParse( $args[ 'description' ] )
		);

		// Other details.
		$out .= Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$this->parser->msg( 'communityrequests-wish-other-details-heading' )->text()
		);
		$user = $this->parser->getUserIdentity();
		$out .= Html::rawElement(
			'ul',
			[],
			$this->getListItem(
				'created',
				$language->userTimeAndDate( $args[ FocusArea::PARAM_CREATED ], $user )
			) .
			$this->getListItem(
				'updated',
				$language->userTimeAndDate( $args[ 'updated' ], $user )
			)
		);

		// Wishes.
		$out .= Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading2' ],
			$this->msg( 'communityrequests-focus-area-wishes-list' )->text()
		);
		$out .= $this->getParagraph(
			'wishes-desc',
			$this->msg( 'communityrequests-focus-area-wishes-description-1' )->text() . ' ' .
			$this->msg( 'communityrequests-focus-area-wishes-description-2' )->text() . ' ' .
			$this->msg( 'communityrequests-focus-area-wishes-description-3' )->text()
		);

		// TODO: implement wishes table somewhere else and re-use it here.

		// Teams and affiliates section.
		if ( isset( $args[ FocusArea::PARAM_OWNERS ] ) || isset( $args[ FocusArea::PARAM_VOLUNTEERS ] ) ) {
			$out .= Html::element(
				'div',
				[ 'class' => 'mw-heading mw-heading2' ],
				$this->msg( 'communityrequests-focus-area-stakeholders' )->text()
			);

			if ( isset( $args[ FocusArea::PARAM_OWNERS ] ) ) {
				$out .= Html::rawElement(
					'div',
					[ 'class' => 'mw-heading mw-heading3' ],
					$this->msg( 'communityrequests-focus-area-owners' )->escaped()
				);
				$out .= $this->getParagraphRaw(
					'owners',
					$this->parser->recursiveTagParse( trim( $args[ FocusArea::PARAM_OWNERS ] ) )
				);
			}

			if ( isset( $args[ FocusArea::PARAM_VOLUNTEERS ] ) ) {
				$out .= Html::rawElement(
					'div',
					[ 'class' => 'mw-heading mw-heading3' ],
					$this->msg( 'communityrequests-focus-area-volunteers' )->escaped()
				);
				$out .= $this->getParagraphRaw(
					'volunteers',
					$this->parser->recursiveTagParse( trim( $args[ FocusArea::PARAM_VOLUNTEERS ] ) )
				);
			}
		}

		// Voting section.
		$out .= $this->getVotingSection(
			$this->config->isFocusAreaVotingEnabled() && in_array(
				trim( $args[ FocusArea::PARAM_STATUS ] ?? '' ),
				$this->config->getStatusWikitextValsEligibleForVoting()
			),
			'focus-area'
		);

		return Html::rawElement( 'div', [ 'class' => 'ext-communityrequests-focus-area' ], $out );
	}

}
