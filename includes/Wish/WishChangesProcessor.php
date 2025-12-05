<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Wish;

use MediaWiki\Extension\CommunityRequests\AbstractChangesProcessor;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Notification\Types\WikiNotification;
use MediaWiki\Title\Title;

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

	/** @inheritDoc */
	protected function getLogEntryFields(): array {
		return array_merge( parent::getLogEntryFields(), [
			Wish::PARAM_FOCUS_AREA => null,
		] );
	}

	/** @inheritDoc */
	protected function getNotificationFields(): array {
		return array_merge( parent::getNotificationFields(), [
			Wish::PARAM_FOCUS_AREA => null,
		] );
	}

	/** @inheritDoc */
	public function notifySubscribers( int $revId ): void {
		if ( !$this->config->isNotificationsEnabled() || !$this->subscriptionStore ) {
			return;
		}
		parent::notifySubscribers( $revId );
		// If the focus area changed, notify focus area subscribers too.
		$changes = $this->getChanges( $this->getNotificationFields() );
		if ( $changes[Wish::PARAM_FOCUS_AREA] ?? null ) {
			$oldFaId = $changes[Wish::PARAM_FOCUS_AREA]['old'];
			$newFaId = $changes[Wish::PARAM_FOCUS_AREA]['new'];
			$this->notifyFocusAreaSubscribers( $oldFaId, $revId, true );
			$this->notifyFocusAreaSubscribers( $newFaId, $revId );
		}
	}

	private function notifyFocusAreaSubscribers( string $faId, int $revId, bool $removed = false ): void {
		if ( !$faId ) {
			return;
		}
		$faPageRef = $this->config->getEntityPageRefFromWikitextVal( $faId );
		if ( !$faPageRef ) {
			return;
		}
		$faIdentity = Title::newFromPageReference( $faPageRef );
		$recipients = $this->locateUsers( $faIdentity );
		if ( count( $recipients ) === 0 ) {
			return;
		}
		$focusArea = $this->getMaybeCachedEntity( $faIdentity, $this->context->getLanguage()->getCode() );
		$notification = new WikiNotification(
			'communityrequests-focus-area-wish-change',
			$faIdentity,
			$this->context->getUser(),
			[
				'entityId' => $faId,
				'entityTitle' => $this->sanitizeValue(
					$focusArea->getPage(),
					FocusArea::PARAM_TITLE,
					$focusArea->getTitle()
				),
				'wishId' => $this->config->getEntityWikitextVal( $this->entity->getPage() ),
				'wishTitle' => $this->sanitizeValue(
					$this->entity->getPage(),
					Wish::PARAM_TITLE,
					$this->entity->getTitle()
				),
				'wishPageTitle' => $this->titleFormatter->getPrefixedText( $this->entity->getPage() ),
				'revId' => $revId,
				'removed' => $removed,
			]
		);
		$this->notifications->notify( $notification, $recipients );
	}

	/** @inheritDoc */
	protected function getNotification( string $field, array $change, int $revId ): WikiNotification {
		$notification = parent::getNotification( $field, $change, $revId );
		// For focus area changes, add the focus area ID and title to notification properties.
		if ( $field === Wish::PARAM_FOCUS_AREA && $change['new'] ) {
			$faPageRef = $this->config->getEntityPageRefFromWikitextVal( $change['new'] );
			if ( !$faPageRef ) {
				return $notification;
			}
			$notification->setProperty( 'focusAreaId', $change['new'] );
			$focusArea = $this->getMaybeCachedEntity(
				Title::newFromPageReference( $faPageRef ),
				$this->context->getLanguage()->getCode()
			);
			$notification->setProperty( 'focusAreaTitle', $focusArea->getTitle() );
		}
		return $notification;
	}
}
