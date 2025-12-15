<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Content\Transform\ContentTransformer;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageParser;
use MediaWiki\Language\Language;
use MediaWiki\Language\LocalizationContext;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Parser\ParserOptions;
use Psr\Log\LoggerInterface;
use Wikimedia\Bcp47Code\Bcp47Code;

/**
 * Service that processes changes between two versions of an entity into a structured format,
 * which can then be used for generating edit summaries, notifications, log entries, and
 * supplying change info to the EditFilterMergedContent hook handler.
 *
 * Use ChangesProcessorFactory to create instances of this class for a new pair of entities.
 *
 * Entrypoints:
 * * ::getChanges() - get a structured array of changes.
 * * ::getEditSummary() - get an automatic edit summary based on the changes.
 * * ::addLogEntries() - add log entries based on the changes.
 */
abstract class AbstractChangesProcessor implements LocalizationContext {

	public function __construct(
		protected readonly WishlistConfig $config,
		protected readonly AbstractWishlistStore $store,
		protected readonly ContentTransformer $transformer,
		protected readonly ?TranslatablePageParser $translatablePageParser,
		protected readonly LoggerInterface $logger,
		protected readonly IContextSource $context,
		protected readonly AbstractWishlistEntity $entity,
		protected readonly ?AbstractWishlistEntity $oldEntity = null,
	) {
	}

	/**
	 * Get a structured array of changes between the old and new entity.
	 * This the main entry point for this class.
	 *
	 * @param ?array $fields Fields to process, merged into ::getFields().
	 *   Keys are AbstractWishlistEntity::PARAM_* constants.
	 *   Values can be null (no processing) or a callback that takes the field value and
	 *   and returns a processed value (i.e. localized message instead of a wikitext value).
	 * @return array Keyed by field name, with 'old' and 'new' values.
	 */
	public function getChanges(
		?array $fields = null
	): array {
		if ( !$this->oldEntity ) {
			// No changes if there is no old entity.
			return [];
		}
		$fields ??= array_merge( $this->getFields(), $fields ?? [] );

		$oldValues = $this->getValuesForChange( $this->oldEntity, $fields );
		$newValues = $this->getValuesForChange( $this->entity, $fields );

		$changesList = [];
		foreach ( array_keys( $fields ) as $field ) {
			$oldValue = $oldValues[$field] ?? null;
			$newValue = $newValues[$field] ?? null;
			if ( $oldValue !== $newValue ) {
				$changesList[$field] = [
					'old' => $oldValue,
					'new' => $newValue,
				];
			}
		}

		return $changesList;
	}

	/**
	 * The fields we care about in reporting changes.
	 *
	 * Keys are AbstractWishlistEntity::PARAM_* constants.
	 * Values can be null (no processing) or a callback that takes the
	 * field value and returns a processed value for the summary.
	 *
	 * Subclasses can override to add more fields, while ::getEditSummaryFields()
	 * and ::getLogEntryFields() can override to change processing for those use cases.
	 *
	 * @return array<string, ?callable>
	 */
	protected function getFields(): array {
		return [
			AbstractWishlistEntity::PARAM_TITLE => null,
			AbstractWishlistEntity::PARAM_DESCRIPTION => null,
			AbstractWishlistEntity::PARAM_STATUS => null,
		];
	}

	/**
	 * Get the values for the given fields from the entity, applying any necessary
	 * transformations (wikitext pre-save, translation markup removal).
	 *
	 * @param AbstractWishlistEntity $entity
	 * @param array<string, ?callable> $fields
	 * @return array
	 */
	private function getValuesForChange( AbstractWishlistEntity $entity, array $fields ): array {
		$entityData = $entity->toArray( $this->config );
		$ret = [];
		foreach ( $fields as $field => $callback ) {
			$value = is_callable( $callback ) ?
				$callback( $entityData[$field] ?? null ) :
				$entityData[$field] ?? null;
			// First do a pre-save transformation, as we will be comparing against the saved value.
			if ( in_array( $field, $this->store->getWikitextFields() ) && !is_array( $value ) ) {
				/** @var WikitextContent $content */
				$content = $this->transformer->preSaveTransform(
					new WikitextContent( $value ?? '' ),
					$entity->getPage(),
					$this->context->getUser(),
					ParserOptions::newFromUserAndLang( $this->context->getUser(), $this->getLanguage() )
				);
				'@phan-var WikitextContent $content';
				$value = $content->getText();
			}
			// Remove translation markup if it is a translatable field.
			if ( in_array( $field, $this->store->getExtTranslateFields() ) &&
				$this->translatablePageParser?->containsMarkup( $value )
			) {
				$value = $this->translatablePageParser->cleanupTags( $value );
			}

			$ret[$field] = $value;
		}
		return $ret;
	}

	// Edit summaries.

	/**
	 * Generate an automatic edit summary based on the fields that changed.
	 *
	 * @return string Localized change messages as a semicolon list.
	 */
	public function getEditSummary(): string {
		if ( !$this->oldEntity ) {
			return $this->getEditSummaryPublish( $this->entity );
		}

		$changesList = [];
		foreach ( $this->getChanges( $this->getEditSummaryFields() ) as $field => $change ) {
			$changesList = array_merge( $changesList,
				$this->getMessagesForFieldChange( $field, $change['new'], $change['old'] )
			);
		}

		return $this->getLanguage()->semicolonList( $changesList );
	}

	/**
	 * Get the fields that, if changed, we want to report in edit summaries.
	 * See ::getFields() for details.
	 *
	 * @return array<string, ?callable>
	 */
	protected function getEditSummaryFields(): array {
		return array_merge( $this->getFields(), [
			AbstractWishlistEntity::PARAM_STATUS => function ( string $status ) {
				// Messages are configurable but by default may include:
				// * communityrequests-status-wish-under-review
				// * communityrequests-status-wish-declined
				// * communityrequests-status-wish-community-opportunity
				// * communityrequests-status-wish-long-term-opportunity
				// * communityrequests-status-wish-near-term-opportunity
				// * communityrequests-status-wish-prioritized
				// * communityrequests-status-wish-in-progress
				// * communityrequests-status-wish-done
				// * communityrequests-status-focus-area-under-review
				// * communityrequests-status-focus-area-declined
				// * communityrequests-status-focus-area-community-opportunity
				// * communityrequests-status-focus-area-long-term-opportunity
				// * communityrequests-status-focus-area-near-term-opportunity
				// * communityrequests-status-focus-area-prioritized
				// * communityrequests-status-focus-area-in-progress
				// * communityrequests-status-focus-area-done
				return $this->msg(
					(string)$this->config->getStatusLabelFromWikitextVal( $this->store->entityType(), $status )
				)->inContentLanguage()->text();
			},
		] );
	}

	/**
	 * Get messages for a change to a specific field.
	 *
	 * @param string $field Field name
	 * @param string|array $value New value
	 * @param string|array|null $oldValue Old value
	 * @return array List of change messages
	 */
	private function getMessagesForFieldChange(
		string $field,
		string|array $value,
		// phpcs:ignore MediaWiki.Usage.NullableType.ExplicitNullableTypes -- false positive
		string|array|null $oldValue = null
	): array {
		$isArrayField = is_array( $value ) || is_array( $oldValue );
		$isWikitextField = in_array( $field, $this->store->getWikitextFields(), true );

		if ( $isWikitextField && !$isArrayField ) {
			// We don't want to include the value of wikitext fields as they can be very large.
			// The following messages may be used here:
			// * communityrequests-entity-summary-description-updated
			// * communityrequests-entity-summary-audience-updated
			// * communityrequests-entity-summary-shortdescription-updated
			// * communityrequests-entity-summary-owners-updated
			// * communityrequests-entity-summary-volunteers-updated
			return [ $this->msg( "communityrequests-entity-summary-$field-updated" )
				->inContentLanguage()
				->text() ];
		} elseif ( $oldValue === null ) {
			return [];
		}

		$msgKey = "communityrequests-entity-summary-{$field}";
		if ( $isArrayField ) {
			$added = array_diff( $value, $oldValue );
			$removed = array_diff( $oldValue, $value );
			$parts = [];
			if ( $added ) {
				// The following messages may be used here:
				// * communityrequests-entity-summary-tags-added
				// * communityrequests-entity-summary-phabtasks-added
				$parts[] = $this->msg(
					"$msgKey-added",
					$this->context->getLanguage()->commaList( $added ),
					count( $added )
				)->inContentLanguage()->text();
			}
			if ( $removed ) {
				// The following messages may be used here:
				// * communityrequests-entity-summary-tags-removed
				// * communityrequests-entity-summary-phabtasks-removed
				$parts[] = $this->msg(
					"$msgKey-removed",
					$this->context->getLanguage()->commaList( $removed ),
					count( $removed )
				)->inContentLanguage()->text();
			}
			return $parts;
		} else {
			// The following messages may be used here:
			// * communityrequests-entity-summary-status-changed
			// * communityrequests-entity-summary-focusarea-changed
			// * communityrequests-entity-summary-title-changed
			// * communityrequests-entity-summary-type-changed
			return [ $this->msg( "$msgKey-changed", $oldValue, $value )->inContentLanguage()->text() ];
		}
	}

	/**
	 * The edit summary to use when publishing a new wishlist entity.
	 *
	 * @param AbstractWishlistEntity $entity
	 * @return string
	 */
	protected function getEditSummaryPublish( AbstractWishlistEntity $entity ): string {
		// The following messages may be used here:
		// * communityrequests-publish-wish-summary
		// * communityrequests-publish-focusarea-summary
		return $this->msg( "communityrequests-publish-{$this->store->entityType()}-summary", $entity->getTitle() )
			->inContentLanguage()->text();
	}

	// Log entries.

	/**
	 * Insert and publish log entries for the changes between the old and new entity.
	 */
	public function addLogEntries(): void {
		if ( !$this->oldEntity ) {
			// Single log entry for creation.
			$this->publishLogEntry(
				$this->getLogEntry( $this->store->entityType() . '-create' )
			);
			return;
		}

		$changes = $this->getChanges( $this->getLogEntryFields() );
		if ( count( $changes ) === 0 ) {
			$this->logger->debug( 'No changes to log for entity ' . $this->entity->getPage() );
			return;
		}

		foreach ( $changes as $field => $change ) {
			$this->logger->debug( "Logging change for field '$field': old={$change['old']}, new={$change['new']}" );
			$logEntry = $this->getLogEntry(
				$this->store->entityType() . "-$field-change",
				$change['new'],
				$change['old']
			);
			$this->publishLogEntry( $logEntry );
		}
	}

	/**
	 * The fields that, if changed, we want to log. See ::getFields() for details.
	 *
	 * For logging, we want i18n transformation isolated in CommunityRequestsLogFormatter so
	 * the language-agnostic wikitext values are stored in the log_params for easier querying.
	 *
	 * @return array<string, ?callable>
	 */
	protected function getLogEntryFields(): array {
		return [
			// Only log status changes, + what's in the subclass overrides (i.e. focus area for wishes).
			AbstractWishlistEntity::PARAM_STATUS => null,
		];
	}

	private function getLogEntry( string $subtype, ?string $new = null, ?string $old = null ): ManualLogEntry {
		$logEntry = new ManualLogEntry( 'communityrequests', $subtype );
		$logEntry->setTarget( $this->entity->getPage() );
		$logEntry->setPerformer( $this->context->getUser() );
		if ( $this->oldEntity ) {
			$logEntry->setParameters( [
				'4::old' => $old,
				'5::new' => $new,
			] );
		}
		return $logEntry;
	}

	private function publishLogEntry( ManualLogEntry $logEntry ): int {
		$logId = $logEntry->insert();
		$logEntry->publish( $logId );
		return $logId;
	}

	// Helper methods implementing LocalizationContext interface.

	/** @inheritDoc */
	public function msg( $key, ...$params ) {
		return $this->context->msg( $key, ...$params );
	}

	public function getLanguage(): Language {
		return $this->context->getLanguage();
	}

	/** @inheritDoc */
	public function getLanguageCode(): Bcp47Code {
		return $this->context->getLanguageCode();
	}
}
