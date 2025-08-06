<?php

namespace MediaWiki\Extension\CommunityRequests\Vote;

use MediaWiki\Extension\CommunityRequests\AbstractTemplateRenderer;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Html\Html;
use MediaWiki\Page\PageReference;

class VoteTemplateRenderer extends AbstractTemplateRenderer {

	protected string $entityType = 'vote';

	protected function getArgAliases(): array {
		return [];
	}

	public function render(): string {
		if ( !$this->config->isWishOrFocusAreaPage( $this->parser->getPage() ) &&
			!$this->config->isVotesPage( $this->parser->getPage() )
		) {
			return '';
		}

		$args = $this->getArgs();
		$entityType = $this->getEntityType( $this->parser->getPage() );

		if ( !$entityType ) {
			$this->logger->debug( __METHOD__ . ": Not a wish or focus area page found. {0}", [ json_encode( $args ) ] );
			return '';
		}

		$missingFields = $this->validateArguments( $args, [ 'username', 'timestamp' ] );
		if ( $missingFields ) {
			return $this->getMissingFieldsErrorMessage( $missingFields );
		}

		$extensionData = $this->parser->getOutput()->getExtensionData( self::EXT_DATA_KEY ) ?? [];
		$extensionData[AbstractWishlistEntity::PARAM_VOTE_COUNT] ??= 0;
		$extensionData[AbstractWishlistEntity::PARAM_VOTE_COUNT]++;
		// Extension data needed for storage in CommunityRequestsHooks::onLinksUpdateComplete().
		// This data may already exist if the page is a wish or focus area page.
		$extensionData['entityType'] ??= $entityType;
		$extensionData['lang'] ??= $this->parser->getTargetLanguage()->getCode();

		$this->logger->debug( __METHOD__ . ": Rendering vote. {0}", [ json_encode( $args ) ] );
		$this->parser->getOutput()->setExtensionData( self::EXT_DATA_KEY, $extensionData );

		return $this->renderVoteInternal( $args[ 'username'], $args['timestamp'], $args['comment'] );
	}

	private function renderVoteInternal( string $username, string $timestamp, string $comment ): string {
		$space = $this->msg( 'word-separator' )->escaped();
		$out = Html::element( 'span', [ 'class' => 'ext-communityrequests-vote-entry--support' ] ) .
			Html::element( 'b', [], $this->msg( 'communityrequests-support-label' )->text() ) .
			$space . $this->parser->recursiveTagParse( $comment ) .
			$space . $this->msg( 'signature', $username, $username )->parse() .
			$space . $this->formatDate( $timestamp );

		return Html::rawElement(
			'div',
			[ 'class' => 'ext-communityrequests-vote-entry' ],
			$out
		);
	}

	private function getEntityType( ?PageReference $identity ): ?string {
		if ( !$identity ) {
			return null;
		}

		if ( $this->config->isVotesPage( $identity ) ) {
			$entity = $this->config->getEntityPageRefFromVotesPage( $identity );
		} else {
			$entity = $identity;
		}

		if ( $this->config->isWishPage( $entity ) ) {
			return 'wish';
		} elseif ( $this->config->isFocusAreaPage( $entity ) ) {
			return 'focus-area';
		}

		return null;
	}
}
