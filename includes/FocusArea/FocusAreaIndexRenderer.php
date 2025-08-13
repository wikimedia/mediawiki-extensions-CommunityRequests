<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\FocusArea;

use MediaWiki\Extension\CommunityRequests\AbstractRenderer;
use MediaWiki\Html\Html;

class FocusAreaIndexRenderer extends AbstractRenderer {
	protected string $entityType = 'focus-area-index';

	/**
	 * Render {{#CommunityRequests: focus-area-index}}
	 *
	 * @return string
	 */
	public function render(): string {
		if ( !$this->config->isEnabled() ) {
			return '';
		}
		$this->parser->addTrackingCategory( self::TRACKING_CATEGORY );
		$outputHTML = '';
		$args = $this->getArgs();

		$this->parser->getOutput()->addModuleStyles( [ 'ext.communityrequests.styles' ] );

		/** @var FocusArea[] $focusAreas */
		$focusAreas = $this->focusAreaStore->getAll(
			$this->getArg( 'lang', $this->parser->getContentLanguage()->getCode() ),
			$this->getArg( 'sort', 'created' ),
			$this->getArg( 'dir', 'descending' ),
			intval( $this->getArg( 'limit', 10 ) )
		);

		foreach ( $focusAreas as $focusArea ) {
			$status = $this->config->getStatuses()[
				$this->config->getStatusWikitextValFromId( $focusArea->getStatus() )
			];

			$statusChipHtml = Html::openElement( 'div' ) .
				Html::rawElement(
					'span',
					[ 'class' => 'cdx-info-chip' ],
					Html::element(
						'span',
						[ 'class' => 'cdx-info-chip__text' ],
						// Messages are configurable. By default they include:
						// * communityrequests-status-draft,
						// * communityrequests-status-submitted,
						// * communityrequests-status-open,
						// * communityrequests-status-in-progress,
						// * communityrequests-status-delivered,
						// * communityrequests-status-blocked,
						// * communityrequests-status-archived,
						// * communityrequests-status-unknown
						$this->msg( $status['label'] )->text()
					)
				) .
				Html::closeElement( 'div' );

			$title = Html::element(
				'div',
				[ 'class' => 'cdx-card__text__title' ],
				$focusArea->getTitle()
			);
			$descriptionHtml = Html::rawElement(
				'div',
				[ 'class' => 'cdx-card__text__description' ],
				$this->parser->recursiveTagParse( $focusArea->getShortDescription() )
			);
			$linkHtml = $this->getFocusAreaLink(
				$focusArea->getPage()->getDBkey(),
				$this->msg( 'communityrequests-focus-area-view-wishes' )->text()
			);
			$linkWrapperHtml = Html::rawElement(
				'div',
				[ 'class' => 'cdx-card__text__supporting-text' ],
				$linkHtml
			);
			$supportingTextHtml = Html::rawElement(
				'div',
				[ 'class' => 'cdx-card__text__supporting-text' ],
				Html::element( 'b', [], $this->msg( 'communityrequests-focus-area-supported' )->text() ) .
				$this->msg( 'word-separator' )->escaped() .
				$this->getFocusAreaLink(
					$focusArea->getPage()->getDBkey(),
					$this->msg(
						'communityrequests-focus-area-supported-val',
						$focusArea->getVoteCount()
					)->text()
				)
			);
			$cardHtml = Html::rawElement(
				'div',
				[ 'class' => 'cdx-card__text' ],
				$statusChipHtml . $title . $descriptionHtml . $supportingTextHtml . $linkWrapperHtml
			);
			$outputHTML .= Html::rawElement(
				'div',
				[ 'class' => 'cdx-card' ],
				$cardHtml
			);
		}

		return HTML::rawElement(
			'div',
			[ 'class' => 'ext-communityrequests-focus-area-index' ],
			$outputHTML
		);
	}
}
