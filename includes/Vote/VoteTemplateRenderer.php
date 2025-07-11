<?php

namespace MediaWiki\Extension\CommunityRequests\Vote;

use MediaWiki\Extension\CommunityRequests\AbstractTemplateRenderer;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Page\PageReference;
use MediaWiki\Title\Title;

class VoteTemplateRenderer extends AbstractTemplateRenderer {

	protected string $entityType = 'vote';

	protected function getArgAliases(): array {
		return array_flip( $this->config->getFocusAreaTemplateParams() );
	}

	public function render(): string {
		if ( !$this->config->isEnabled()
			|| !$this->config->isVotePage( $this->parser->getPage() ) ) {
			return '';
		}

		$args = $this->getArgs();
		$args['entityType'] = $this->getEntityType( $this->parser->getPage() );

		if ( !$args['entityType'] ) {
			$this->logger->debug( __METHOD__ . ": Not a wish or focus area page found. {0}", [ json_encode( $args ) ] );
			return '';
		}

		$extensionData = $this->parser->getOutput()->getExtensionData( self::EXT_DATA_KEY );
		$args[AbstractWishlistEntity::PARAM_VOTE_COUNT]
			= ( $extensionData[AbstractWishlistEntity::PARAM_VOTE_COUNT] ?? 0 ) + 1;

		$this->logger->debug( __METHOD__ . ": Rendering vote. {0}", [ json_encode( $args ) ] );
		$this->parser->getOutput()->setExtensionData( self::EXT_DATA_KEY, $args );

		return '';
	}

	private function getEntityType( ?PageReference $identity ): ?string {
		if ( !$identity ) {
			return null;
		}

		$basePage = Title::newFromPageReference( $identity )->getBaseTitle();
		if ( $basePage->exists() ) {
			$identity = $basePage->toPageIdentity();
		}

		if ( $this->config->isWishPage( $identity ) ) {
			return 'wish';
		} elseif ( $this->config->isFocusAreaPage( $identity ) ) {
			return 'focus-area';
		}

		return null;
	}
}
