<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Vote;

use MediaWiki\Extension\CommunityRequests\AbstractRenderer;
use MediaWiki\Html\Html;
use MediaWiki\Page\PageReference;
use Wikimedia\Parsoid\Core\MergeStrategy;

class VoteRenderer extends AbstractRenderer {

	public const EXT_DATA_VOTE_COUNT = 'CommunityRequests-vote-count';

	protected string $rendererType = 'vote';

	public function render(): string {
		if ( !$this->config->isVotesPage( $this->parser->getPage() ) ) {
			return '';
		}

		$args = $this->getArgs();
		$entityType = $this->getEntityType( $this->parser->getPage() );

		if ( !$entityType ) {
			$this->logger->debug( __METHOD__ . ": Not a wish or focus area page found. {0}", [ json_encode( $args ) ] );
			return '';
		}

		$missingFields = $this->validateArguments( $args, [ 'timestamp' ] );
		if ( $missingFields ) {
			return $this->getMissingFieldsErrorMessage( $missingFields );
		}
		// Must have either a 'username' or 'userid' field.
		$username = $args['username'] ?? null;
		if ( isset( $args['userid'] ) && is_numeric( $args['userid'] ) ) {
			$username = $this->userFactory->newFromId( (int)$args['userid'] )?->getName();
		}
		if ( !$username ) {
			// We want to only use userid moving forward, so advertise that as the missing field.
			return $this->getMissingFieldsErrorMessage( [ 'userid' ] );
		}

		$this->logger->debug( __METHOD__ . ": Recording vote tally for $username" );
		$this->parser->getOutput()->appendExtensionData( self::EXT_DATA_VOTE_COUNT, 1, MergeStrategy::SUM );

		return $this->renderVoteInternal( $username, $args['timestamp'], $args['comment'] );
	}

	private function renderVoteInternal( string $username, string $timestamp, string $comment ): string {
		$this->logger->debug( __METHOD__ . ": Rendering vote. {0}", [ json_encode( func_get_args() ) ] );

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
