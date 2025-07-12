<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\CommunityRequests\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Extension\CommunityRequests\AbstractWishlistEntity;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusArea;
use MediaWiki\Extension\CommunityRequests\FocusArea\FocusAreaStore;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\StringDef;

class ApiFocusAreaEdit extends ApiWishlistEntityBase {

	/** @inheritDoc */
	protected function executeWishlistEntity(): void {
		if ( !$this->config->isFocusAreaPage( $this->title ) ) {
			$this->dieWithError( 'apierror-focusareaedit-notafocusarea' );
		}

		$focusArea = FocusArea::newFromWikitextParams(
			$this->title,
			// Edits are only made to the base language page.
			$this->params[ 'baselang' ],
			$this->params,
			$this->config
		);

		$saveStatus = $this->save(
			$focusArea->toWikitext( $this->config ),
			$this->getEditSummary( $focusArea ),
			$this->params[ 'token' ],
			$this->params[ 'baserevid' ] ?? null
		);

		if ( $saveStatus->isOK() === false ) {
			$this->dieWithError( $saveStatus->getMessages() );
		}

		$resultData = $saveStatus->getValue()->getResultData()[ 'edit' ];
		// ApiEditPage adds the 'title' key to the result data, but we want to use 'focusarea'.
		$resultData[ 'focusarea' ] = $resultData[ 'title' ];
		unset( $resultData[ 'title' ] );
		// 'newtimestamp' should be 'updated'.
		$resultData[ 'updated' ] = $resultData[ 'newtimestamp' ];
		unset( $resultData[ 'newtimestamp' ] );
		$ret = $resultData + $focusArea->toArray( $this->config, true );
		$this->getResult()->addValue( null, $this->getModuleName(), $ret );
	}

	/** @inheritDoc */
	public function getEditSummary( AbstractWishlistEntity $entity ): string {
		return trim( $this->params[ 'focusarea' ] ?? '' ) ? $this->editSummarySave() : $this->editSummaryPublish();
	}

	/** @inheritDoc */
	protected function editSummaryPublish(): string {
		return $this->msg( 'communityrequests-publish-focus-area-summary', $this->params['title'] )
			->inContentLanguage()
			->text();
	}

	/** @inheritDoc */
	protected function editSummarySave(): string {
		return $this->msg( 'communityrequests-save-focus-area-summary', $this->params['title'] )
			->inContentLanguage()
			->text();
	}

	/** @inheritDoc */
	protected function getWishlistEntityTitle(): Title {
		if ( isset( $this->params[ 'focusarea' ] ) ) {
			return Title::newFromText(
				$this->config->getFocusAreaPagePrefix() .
				$this->store->getIdFromInput( $this->params[ 'focusarea' ] )
			);
		} else {
			// If this is a new focus area, generate a new ID and page title.
			$id = $this->store->getNewId();
			return Title::newFromText( $this->config->getFocusAreaPagePrefix() . $id );
		}
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'csrf';
	}

	/** @inheritDoc */
	public function isInternal() {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		// NOTE: Keys should match the FocusArea::PARAM_* constants where possible.
		return [
			'focusarea' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'status' => [
				ParamValidator::PARAM_TYPE => array_keys( $this->config->getStatuses() ),
				ParamValidator::PARAM_REQUIRED => true,
			],
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				StringDef::PARAM_MAX_BYTES => FocusAreaStore::TITLE_MAX_BYTES,
			],
			'description' => [
				ParamValidator::PARAM_TYPE => 'text',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'shortdescription' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			'owners' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			'volunteers' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			'created' => [
				ParamValidator::PARAM_TYPE => 'timestamp',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'baselang' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'baserevid' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ApiBase::PARAM_HELP_MSG => 'apihelp-edit-param-baserevid',
			],
		];
	}
}
