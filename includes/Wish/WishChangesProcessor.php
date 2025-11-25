<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Extension\CommunityRequests\AbstractChangesProcessor;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;

class WishChangesProcessor extends AbstractChangesProcessor {

	/** @inheritDoc */
	protected function getFields(): array {
		return array_merge( parent::getFields(), [
			Wish::PARAM_TYPE => null,
			Wish::PARAM_FOCUS_AREA => null,
			Wish::PARAM_TAGS => null,
			Wish::PARAM_AUDIENCE => null,
			Wish::PARAM_PHAB_TASKS => null,
			Wish::PARAM_PROPOSER => null,
		] );
	}

	/** @inheritDoc */
	protected function getEditSummaryFields(): array {
		return array_merge( parent::getEditSummaryFields(), [
			Wish::PARAM_TYPE => fn ( string $type ) => $this->msg(
				$this->config->getWishTypeLabelFromWikitextVal( $type ) . '-label'
			)->inContentLanguage()->text(),
			Wish::PARAM_FOCUS_AREA => function ( string $focusArea ) {
				if ( !$focusArea ) {
					return $this->msg( 'communityrequests-focus-area-unassigned' )->inContentLanguage()->text();
				}
				$faPage = $this->config->getEntityPageRefFromWikitextVal( $focusArea );
				return '[[' . $faPage->getDBkey() . '|' . $focusArea . ']]';
			},
			Wish::PARAM_TAGS => function ( array $tags ) {
				return array_map(
					fn ( string $tagWikitextVal ) => $this->msg(
						(string)$this->config->getTagLabelFromWikitextVal( $tagWikitextVal )
					)->inContentLanguage()->text(),
					$tags
				);
			},
			Wish::PARAM_PHAB_TASKS => function ( array $tasks ) {
				return array_map(
					static fn ( string $taskId ) => "[[phab:$taskId|$taskId]]",
					$tasks
				);
			},
		] );
	}

	/** @inheritDoc */
	protected function getEditSummaryPublish( /** @var Wish $entity */ AbstractWishlistEntity $entity ): string {
		'@phan-var Wish $entity';
		$summary = parent::getEditSummaryPublish( $entity );
		// If there are Phabricator tasks, append them to the edit summary.
		if ( count( $entity->getPhabTasks() ) ) {
			$summary .= ' ' .
				$this->msg( 'parentheses-start' )->inContentLanguage()->text() .
				$this->getLanguage()->commaList( array_map(
					static fn ( int $taskId ) => "[[phab:T$taskId|T$taskId]]",
					$entity->getPhabTasks()
				) ) .
				$this->msg( 'parentheses-end' )->inContentLanguage()->text();
		}
		return $summary;
	}
}
