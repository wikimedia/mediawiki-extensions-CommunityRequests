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

		$this->parser->getOutput()->addModuleStyles( [ 'ext.communityrequests.styles' ] );

		/** @var FocusArea[] $focusAreas */
		$focusAreas = $this->focusAreaStore->getAll(
			$this->getArg( 'lang', $this->parser->getContentLanguage()->getCode() ),
			$this->getArg( 'sort', 'created' ),
			$this->getArg( 'dir', 'descending' ),
			intval( $this->getArg( 'limit', 10 ) ),
			null,
			[],
			$this->focusAreaStore::FETCH_WIKITEXT_TRANSLATED
		);

		foreach ( $focusAreas as $focusArea ) {
			'@phan-var FocusArea $focusArea';

			$statusChipHtml = Html::openElement( 'div' ) .
				$this->getStatusChipHtml(
					$this->config->getStatusWikitextValFromId( $focusArea->getStatus() )
				) .
				Html::closeElement( 'div' );

			$title = Html::element(
				'div',
				[ 'class' => 'cdx-card__text__title ext-communityrequests-focus-area-card__title' ],
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
				[ 'class' => 'cdx-card ext-communityrequests-focus-area-card' ],
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
