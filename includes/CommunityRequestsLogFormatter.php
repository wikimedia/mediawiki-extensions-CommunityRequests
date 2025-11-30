<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Extension\CommunityRequests\Wish\WishStore;
use MediaWiki\Logging\LogEntry;
use MediaWiki\Logging\LogFormatter;
use MediaWiki\Message\Message;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleFormatter;
use Psr\Log\LoggerInterface;
use Wikimedia\Message\ScalarParam;

/**
 * This class formats CommunityRequests log entries.
 *
 * Used in the following messages:
 * - log-entry-communityrequests-wish-create
 * - log-entry-communityrequests-wish-status-change
 * - log-entry-communityrequests-wish-focusarea-change
 * - log-entry-communityrequests-focus-area-create
 * - log-entry-communityrequests-focus-area-status-change
 */
class CommunityRequestsLogFormatter extends LogFormatter {

	use WishlistEntityTrait;

	public function __construct(
		LogEntry $entry,
		protected readonly WishlistConfig $config,
		protected readonly WishStore $wishStore,
		protected readonly FocusAreaStore $focusAreaStore,
		protected readonly TitleFormatter $titleFormatter,
		protected readonly TitleFactory $titleFactory,
		protected readonly LoggerInterface $logger,
	) {
		parent::__construct( $entry );
	}

	/** @inheritDoc */
	protected function getMessageParameters() {
		$params = parent::getMessageParameters();
		$entryParams = $this->entry->getParameters();

		// Change the 'target' to be a link to the entity with the localized title.
		$entity = $this->getMaybeCachedEntity( $this->entry->getTarget(), $this->getContentLanguage()->getCode() );
		if ( !$entity ) {
			$this->logger->error(
				__METHOD__ . ': Could not load entity for log entry with target {0}',
				[ $this->entry->getTarget()->toPageIdentity()->__toString() ]
			);
			return $params;
		}
		$params[2] = Message::rawParam(
			$this->getEntityLink( $entity, $this->context )
		);

		switch ( $this->entry->getSubtype() ) {
			case 'wish-create':
			case 'focus-area-create':
				// No additional parameters needed.
				break;
			case 'wish-status-change':
				// There is no valid "Unknown" status, but it's possible log entries
				// becomes outdated with configuration so we need a fallback.
				$unknown = 'communityrequests-status-wish-unknown';
				$oldStatus = $this->config->getStatusLabelFromWikitextVal( 'wish', $entryParams['4::old'] );
				$newStatus = $this->config->getStatusLabelFromWikitextVal( 'wish', $entryParams['5::new'] );
				$params[3] = $this->msg( $oldStatus ?: $unknown )->inUserLanguage()->text();
				$params[4] = $this->msg( $newStatus ?: $unknown )->inUserLanguage()->text();
				break;
			case 'focus-area-status-change':
				$unknown = 'communityrequests-status-focusarea-unknown';
				$oldStatus = $this->config->getStatusLabelFromWikitextVal( 'focus-area', $entryParams['4::old'] );
				$newStatus = $this->config->getStatusLabelFromWikitextVal( 'focus-area', $entryParams['5::new'] );
				$params[3] = $this->msg( $oldStatus ?: $unknown )->inUserLanguage()->text();
				$params[4] = $this->msg( $newStatus ?: $unknown )->inUserLanguage()->text();
				break;
			case 'wish-focusarea-change':
				$params[3] = $this->getFocusAreaMessage( $entryParams['4::old'] );
				$params[4] = $this->getFocusAreaMessage( $entryParams['5::new'] );
				break;
			default:
				$this->logger->error(
					__METHOD__ . ': Unknown log entry subtype {0} for log entry with target {1}',
					[ $this->entry->getSubtype(), $this->entry->getTarget()->toPageIdentity()->__toString() ]
				);
		}

		// Bad things happen if the numbers are not in correct order
		ksort( $params );

		return $params;
	}

	/** @inheritDoc */
	public function getPreloadTitles() {
		if ( $this->entry->getSubtype() !== 'wish-focusarea-change' ) {
			return [];
		}
		$entryParams = $this->entry->getParameters();
		$titles = [];
		if ( $entryParams['4::old'] ) {
			$focusAreaRef1 = $this->config->getEntityPageRefFromWikitextVal( $entryParams['4::old'] );
			if ( $focusAreaRef1 ) {
				$titles[] = $this->titleFactory->newFromPageReference( $focusAreaRef1 );
			}
		}
		if ( $entryParams['5::new'] ) {
			$focusAreaRef2 = $this->config->getEntityPageRefFromWikitextVal( $entryParams['5::new'] );
			if ( $focusAreaRef2 ) {
				$titles[] = $this->titleFactory->newFromPageReference( $focusAreaRef2 );
			}
		}
		return $titles;
	}

	private function getFocusAreaMessage( ?string $val ): ?ScalarParam {
		if ( $val === null ) {
			return Message::plaintextParam(
				$this->msg( 'communityrequests-focus-area-unassigned' )->inUserLanguage()->text()
			);
		}
		$focusArea = $this->config->getEntityPageRefFromWikitextVal( $val );
		$plaintextParam = Message::plaintextParam( $val );
		if ( $focusArea === null ) {
			// Fallback to wikitext value, perhaps the focus area has been deleted.
			return $plaintextParam;
		}
		$focusAreaEntity = $this->getMaybeCachedEntity(
			$this->titleFactory->newFromPageReference( $focusArea ),
			$this->getContentLanguage()->getCode()
		);
		return $focusAreaEntity ?
			Message::rawParam( $this->getEntityLink( $focusAreaEntity, $this->context ) ) :
			$plaintextParam;
	}
}
