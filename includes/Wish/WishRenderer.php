<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Extension\CommunityRequests\AbstractRenderer;
use MediaWiki\Html\Html;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\TitleValue;

class WishRenderer extends AbstractRenderer {
	public const WISH_TRACKING_CATEGORY = 'communityrequests-wish-category';

	protected string $entityType = 'wish';

	/**
	 * Render {{#CommunityRequests: wish}}
	 *
	 * @return string HTML
	 */
	public function render(): string {
		if ( !$this->config->isEnabled() || !$this->config->isWishPage( $this->parser->getPage() ) ) {
			return '';
		}

		$this->addTrackingCategory( self::WISH_TRACKING_CATEGORY );
		$this->parser->getOptions()->setSuppressSectionEditLinks();

		$args = $this->getArgs();

		$requiredFields = [
			Wish::PARAM_CREATED,
			Wish::PARAM_TITLE,
			Wish::PARAM_PROPOSER,
			Wish::PARAM_BASE_LANG,
		];

		$missingFields = $this->validateArguments( $args, $requiredFields );
		if ( $missingFields ) {
			return $this->getMissingFieldsErrorMessage( $missingFields );
		}

		// Validate the proposer.
		$proposerActorId = $this->wishStore->getActorId( $args[Wish::PARAM_PROPOSER] );
		if ( $proposerActorId === null ) {
			$this->parser->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
			return Html::element(
				'span',
				[ 'class' => 'error' ],
				$this->msg( 'communityrequests-error-invalid-proposer', $args[Wish::PARAM_PROPOSER] )->text()
			);
		}

		// These need to be set here because we need them for display in ::renderWishInternal().
		$args[Wish::PARAM_UPDATED] = $this->parser->getRevisionTimestamp();
		$args[Wish::PARAM_CREATED] ??= $args[Wish::PARAM_UPDATED];

		$args[Wish::PARAM_ENTITY_TYPE] = 'wish';
		$args[Wish::PARAM_LANG] = $this->parser->getTargetLanguage()->getCode();

		// Cache the wish data for storage after the links update.
		$this->parser->getOutput()->setExtensionData( self::EXT_DATA_KEY, $args );

		$languageLinks = ExtensionRegistry::getInstance()->isLoaded( 'Translate' ) ?
			$this->parser->recursiveTagParse( '<languages/>' ) : '';

		return $languageLinks . $this->renderWishInternal( $args );
	}

	private function renderWishInternal( array $args ): string {
		$language = $this->parser->getTargetLanguage();

		// Title and status.
		$this->setDisplayTitleAndIndicator();

		// Description.
		$descHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading2' ],
			$this->msg( 'communityrequests-wish-description-heading' )->text()
		);
		$descHtml = $this->getDivRaw(
			'description',
			$this->parser->recursiveTagParse( $args[Wish::PARAM_DESCRIPTION] ?? '' )
		);

		// Focus area.
		$focusAreaHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$this->msg( 'communityrequests-wish-focus-area-heading' )->text()
		);
		$focusArea = $this->getDivRaw(
			'focus-area',
			$this->getFocusAreaLink( $args[Wish::PARAM_FOCUS_AREA] ?? null )
		);

		// Wish type.
		$wishTypeHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$this->msg( 'communityrequests-wish-type-heading' )->text()
		);
		$wishType = $this->getDiv(
			'wish-type',
			$this->msg(
				$this->config->getWishTypeLabelFromWikitextVal( $this->getArg( Wish::PARAM_TYPE, '' ) ) . '-label'
			)->text()
		);

		// Tags.
		$tagsArgs = array_filter(
			explode( WishStore::ARRAY_DELIMITER_WIKITEXT, $this->getArg( Wish::PARAM_TAGS, '' ) )
		);
		$tagLabels = [];
		foreach ( $tagsArgs as $tag ) {
			$label = $this->config->getTagLabelFromWikitextVal( $tag );
			if ( $label === null ) {
				$this->parser->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
				continue;
			}
			$tagLabels[] = $this->msg( $label )->text();
			$tagCategory = $this->config->getTagCategoryFromWikitextVal( $tag );
			if ( $tagCategory ) {
				$this->addTranslationCategory( $tagCategory );
			}
		}

		$tags = '';
		$tagsHeading = '';
		if ( count( $tagLabels ) > 0 ) {
			$tagsHeading = Html::element(
				'div',
				[ 'class' => 'mw-heading mw-heading3', 'id' => 'tags' ],
				$this->msg( 'communityrequests-tags-heading' )->text()
			);

			// @phan-suppress-next-line SecurityCheck-DoubleEscaped
			$tags = $this->getDiv( 'tags', $language->commaList( array_filter( $tagLabels ) ) );
		}

		// Audience.
		$audienceHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$this->msg( 'communityrequests-wish-audience-heading' )->text()
		);
		$audienceHtml = $this->getDivRaw(
			'audience',
			$this->parser->recursiveTagParse(
				$this->getArg( Wish::PARAM_AUDIENCE, '' )
			)
		);

		// Phabricator tasks.
		$tasks = array_filter( array_map( function ( $task ) {
			$task = trim( $task );
			if ( $task === '' ) {
				return null;
			}
			if ( !preg_match( '/^T\d+$/', $task ) ) {
				$this->parser->addTrackingCategory( self::ERROR_TRACKING_CATEGORY );
				return null;
			}
			return $this->linkRenderer->makeLink(
				new TitleValue( NS_MAIN, $task, '', 'phab' ),
				$task
			);
		}, explode( WishStore::ARRAY_DELIMITER_WIKITEXT, $this->getArg( Wish::PARAM_PHAB_TASKS, '' ) ) ) );
		$tasksHeading = '';
		$tasksHtml = '';
		if ( count( $tasks ) ) {
			$tasksHeading = Html::element(
				'div',
				[ 'class' => 'mw-heading mw-heading3' ],
				$this->msg( 'communityrequests-wish-phabricator-heading' )->text()
			);
			$tasksHtml = $this->getDivRaw(
				'phabtasks',
				$language->commaList( array_filter( $tasks ) )
			);
		}

		// Other details.
		$detailsHeading = Html::element(
			'div',
			[ 'class' => 'mw-heading mw-heading3' ],
			$this->msg( 'communityrequests-wish-other-details-heading' )->text()
		);
		$proposerVal = $this->getArg( Wish::PARAM_PROPOSER, '' );

		$detailsHtml = Html::rawElement(
			'ul',
			[],
			$this->getListItem(
				'created',
				$this->formatDate( $args[Wish::PARAM_CREATED] )
			) .
			$this->getListItem(
				'updated',
				$this->formatDate( $args[Wish::PARAM_UPDATED] )
			) .
			$this->getListItem(
				'proposer',
				$this->msg( 'signature', $proposerVal, $proposerVal )->text()
			)
		);

		return Html::rawElement( 'div', [ 'class' => 'ext-communityrequests-wish' ],
			$descHeading . $descHtml .
			$focusAreaHeading . $focusArea .
			$wishTypeHeading . $wishType .
			$tagsHeading . $tags .
			$audienceHeading . $audienceHtml .
			$tasksHeading . $tasksHtml .
			$detailsHeading . $detailsHtml .
			$this->getVotingSection( $this->config->isWishVotingEnabled() )
		);
	}
}
