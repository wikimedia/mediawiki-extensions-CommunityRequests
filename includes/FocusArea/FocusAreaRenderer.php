<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\FocusArea;

use MediaWiki\Extension\CommunityRequests\AbstractRenderer;
use MediaWiki\Html\Html;

class FocusAreaRenderer extends AbstractRenderer {
	public const FOCUS_AREA_TRACKING_CATEGORY = 'communityrequests-focus-area-category';

	protected string $rendererType = 'focus-area';

	/**
	 * Render {{#CommunityRequests: focus-area}}
	 *
	 * @return string HTML
	 */
	public function render(): string {
		if ( !$this->config->isEnabled() || !$this->config->isFocusAreaPage( $this->parser->getPage() ) ) {
			return '';
		}
		$args = $this->getArgs();
		$args[FocusArea::PARAM_DESCRIPTION] ??= '';

		$this->addTrackingCategory( self::FOCUS_AREA_TRACKING_CATEGORY );
		$this->parser->getOptions()->setSuppressSectionEditLinks();

		// Add tracking category for missing critical data.
		$requiredFields = [
			FocusArea::PARAM_CREATED,
			FocusArea::PARAM_TITLE,
			FocusArea::PARAM_BASE_LANG,
		];

		$missingFields = $this->validateArguments( $args, $requiredFields );
		if ( $missingFields ) {
			return $this->getMissingFieldsErrorMessage( $missingFields );
		}

		// These need to be set here because we need them for display in ::renderFocusAreaInternal().
		$args[FocusArea::PARAM_UPDATED] = $this->parser->getRevisionTimestamp();
		$args[FocusArea::PARAM_CREATED] ??= $args[FocusArea::PARAM_UPDATED];

		$args[FocusArea::PARAM_ENTITY_TYPE] = 'focus-area';
		$args[FocusArea::PARAM_LANG] = $this->parser->getTargetLanguage()->getCode();

		$this->logger->debug( __METHOD__ . ": Rendering focus area. {0}", [ json_encode( $args ) ] );
		$this->parser->getOutput()->setExtensionData( self::EXT_DATA_KEY, $args );

		return $this->getEntityTopSection() . $this->renderFocusAreaInternal( $args );
	}

	private function renderFocusAreaInternal( array $args ): string {
		$out = '';

		// Title and status.
		$this->setDisplayTitleAndIndicator();

		// Description.
		$out .= Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading2' ],
			$this->parser->msg( 'communityrequests-wish-description-heading' )->text()
		);
		$out .= $this->getDivRaw(
			'description',
			$this->parser->recursiveTagParse( $this->getArg( FocusArea::PARAM_DESCRIPTION, '' ) )
		);

		// Other details.
		$out .= Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$this->parser->msg( 'communityrequests-wish-other-details-heading' )->text()
		);
		$out .= Html::rawElement(
			'ul',
			[],
			$this->getListItem(
				'created',
				$this->formatDate( $args[FocusArea::PARAM_CREATED] )
			) .
			$this->getListItem(
				'updated',
				$this->formatDate( $args[FocusArea::PARAM_UPDATED] )
			)
		);

		// Wishes.
		$out .= Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading2', 'id' => self::LINK_FRAGMENT_WISHES ],
			$this->msg( 'communityrequests-focus-area-wishes-list' )->text()
		);
		$out .= $this->getDiv(
			'wishes-desc',
			$this->msg( 'communityrequests-focus-area-wishes-description-1' )->text() . ' ' .
			$this->msg( 'communityrequests-focus-area-wishes-description-2' )->text() . ' ' .
			$this->msg( 'communityrequests-focus-area-wishes-description-3' )->text()
		);

		$out .= $this->parser->recursiveTagParse(
			'{{#CommunityRequests: wish-index' .
			'|focusareas=' . $this->config->getEntityWikitextVal( $this->parser->getPage() ) .
			'|sort=created' .
			'|dir=descending' .
			'|limit=10' .
			'}}'
		);

		// Teams and affiliates section.
		$owners = $this->getArg( FocusArea::PARAM_OWNERS, '' );
		$volunteers = $this->getArg( FocusArea::PARAM_VOLUNTEERS, '' );
		if ( $owners || $volunteers ) {
			$out .= Html::element(
				'div',
				[ 'class' => 'mw-heading mw-heading2' ],
				$this->msg( 'communityrequests-focus-area-stakeholders' )->text()
			);

			if ( $owners ) {
				$out .= Html::rawElement(
					'div',
					[ 'class' => 'mw-heading mw-heading3' ],
					$this->msg( 'communityrequests-focus-area-owners' )->escaped()
				);
				$out .= $this->getDivRaw(
					'owners',
					$this->parser->recursiveTagParse( $owners )
				);
			}

			if ( $volunteers ) {
				$out .= Html::rawElement(
					'div',
					[ 'class' => 'mw-heading mw-heading3' ],
					$this->msg( 'communityrequests-focus-area-volunteers' )->escaped()
				);
				$out .= $this->getDivRaw(
					'volunteers',
					$this->parser->recursiveTagParse( $volunteers )
				);
			}
		}

		// Voting section.
		$out .= $this->getVotingSection( $this->config->isFocusAreaVotingEnabled() );

		return Html::rawElement( 'div', [ 'class' => 'ext-communityrequests-focus-area' ], $out );
	}

}
