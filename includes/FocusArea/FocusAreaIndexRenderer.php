<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\FocusArea;

use MediaWiki\Extension\CommunityRequests\AbstractRenderer;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Html\Html;
use MediaWiki\Title\Title;
use Wikimedia\HtmlArmor\HtmlArmor;

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
		// Add wish counts, for later replacement in CommunityRequestsHooks::onParserAfterTidy().
		$this->parser->getOutput()->setExtensionData( self::EXT_DATA_KEY, [
			AbstractWishlistEntity::PARAM_WISH_COUNT => $this->focusAreaStore->getWishCounts()
		] );
		foreach ( $focusAreas as $focusArea ) {
			'@phan-var FocusArea $focusArea';
			$faLinkTarget = Title::newFromPageReference( $focusArea->getPage() );

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
			$linkHtml = $this->linkRenderer->makeKnownLink(
				$faLinkTarget,
				// The strip marker should not be escaped.
				new HtmlArmor( self::getWishCountStripMarker( $focusArea->getPage()->getId() ) )
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
