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
		if ( !$this->getPermissionManager()->userHasRight( $this->getUser(), 'manage-wishlist' ) ) {
			$this->dieWithError( 'apierror-focusareaedit-nopermission' );
		}

		$focusArea = FocusArea::newFromWikitextParams(
			$this->title,
			// Edits are only made to the base language page.
			$this->params[FocusArea::PARAM_BASE_LANG],
			$this->params,
			$this->config
		);

		// Confirm we can parse and then re-create the same wikitext.
		$wikitext = $focusArea->toWikitext( $this->config );
		$validateWish = FocusArea::newFromWikitextParams(
			$focusArea->getPage(),
			$focusArea->getBaseLang(),
			(array)$this->store->getDataFromWikitextContent( $wikitext ),
			$this->config,
		);
		if ( $wikitext->getText() !== $validateWish->toWikitext( $this->config )->getText() ) {
			$this->dieWithError( 'apierror-wishlist-entity-parse' );
		}

		$saveStatus = $this->save(
			$focusArea,
			$this->params['token'],
			$this->params[FocusArea::PARAM_BASE_REV_ID] ?? null
		);

		if ( $saveStatus->isOK() === false ) {
			$this->dieWithError( $saveStatus->getMessages() );
		}

		$resultData = $saveStatus->getValue()->getResultData()['edit'];
		// ApiEditPage adds the 'title' key to the result data, but we want to use 'focusarea'.
		$resultData['focusarea'] = $resultData['title'];
		unset( $resultData['title'] );
		// 'newtimestamp' should be 'updated'.
		$resultData[FocusArea::PARAM_UPDATED] = $resultData['newtimestamp'];
		unset( $resultData['newtimestamp'] );
		$ret = $resultData + $focusArea->toArray( $this->config );
		$this->getResult()->addValue( null, $this->getModuleName(), $ret );
	}

	/** @inheritDoc */
	public function getEditSummary( AbstractWishlistEntity $entity ): string {
		return trim( $this->params['focusarea'] ?? '' ) ? $this->editSummarySave() : $this->editSummaryPublish();
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
		if ( isset( $this->params['focusarea'] ) ) {
			return Title::newFromText(
				$this->config->getFocusAreaPagePrefix() .
				$this->store->getIdFromInput( $this->params['focusarea'] )
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
			FocusArea::PARAM_STATUS => [
				ParamValidator::PARAM_TYPE => array_keys( $this->config->getStatuses() ),
				ParamValidator::PARAM_REQUIRED => true,
			],
			FocusArea::PARAM_TITLE => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				StringDef::PARAM_MAX_BYTES => FocusAreaStore::TITLE_MAX_BYTES,
			],
			FocusArea::PARAM_DESCRIPTION => [
				ParamValidator::PARAM_TYPE => 'text',
				ParamValidator::PARAM_REQUIRED => true,
			],
			FocusArea::PARAM_SHORT_DESCRIPTION => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			FocusArea::PARAM_OWNERS => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			FocusArea::PARAM_VOLUNTEERS => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			FocusArea::PARAM_CREATED => [
				ParamValidator::PARAM_TYPE => 'timestamp',
				ParamValidator::PARAM_REQUIRED => true,
			],
			FocusArea::PARAM_BASE_LANG => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			FocusArea::PARAM_BASE_REV_ID => [
				ParamValidator::PARAM_TYPE => 'integer',
				ApiBase::PARAM_HELP_MSG => 'apihelp-edit-param-baserevid',
			],
		];
	}
}
