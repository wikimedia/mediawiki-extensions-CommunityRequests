<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CommunityRequests;

use MediaWiki\Extension\CommunityRequests\Wish\Wish;
use MediaWiki\Title\Title;

class EntityDataRenderer extends AbstractRenderer {

	use WishlistEntityTrait;

	protected string $rendererType = 'entityData';
	private const ALLOWED_FIELDS = [
		'status' => AbstractWishlistEntity::PARAM_STATUS,
		'title' => AbstractWishlistEntity::PARAM_TITLE,
		'focus_area' => Wish::PARAM_FOCUS_AREA,
		'proposer' => Wish::PARAM_PROPOSER,
		'vote_count' => AbstractWishlistEntity::PARAM_VOTE_COUNT,
		'title_lang' => AbstractWishlistEntity::PARAM_BASE_LANG,
	];

	public function render(): string {
		$args = $this->getArgs();
		$missingFields = $this->validateArguments( $args, [ 'id', 'field' ] );
		if ( $missingFields ) {
			return $this->getMissingFieldsErrorMessage( $missingFields );
		}

		$entityId = trim( $args['id'] );
		$field = trim( $args['field'] );
		$lang = trim( $args['lang'] ?? $this->parser->getTargetLanguage()->getCode() );
		$entityPageRef = $this->config->getEntityPageRefFromWikitextVal( $entityId );

		if ( !$entityPageRef ) {
			$this->logger->debug( __METHOD__ . ": Invalid entity id provided. {0}", [ $entityId ] );
			return $this->getErrorMessage( 'communityrequests-error-invalid-entity-id', $entityId );
		}

		if ( !array_key_exists( $field, self::ALLOWED_FIELDS ) ) {
			$this->logger->debug( __METHOD__ . ": Invalid field requested. {0}", [ $field ] );
			return $this->getErrorMessage(
				'communityrequests-error-invalid-entity-field',
				$this->parser->getTargetLanguage()->commaList( array_keys( self::ALLOWED_FIELDS ) )
			);
		}

		$store = $this->getStoreForPage( $entityPageRef );
		$entityTitle = Title::newFromPageReference( $entityPageRef );
		$entity = $this->getMaybeCachedEntity( $entityTitle, $lang );
		if ( !$entity ) {
			$this->logger->debug( __METHOD__ . ": Entity not found. $entityPageRef" );
			return $this->getErrorMessageRaw(
				"communityrequests-{$store->entityType()}-not-found",
				[ $entityTitle->getPrefixedText(), $entityId ]
			);
		}

		return strval( $entity->toArray( $this->config )[self::ALLOWED_FIELDS[ $field ]] ?? '' );
	}
}
